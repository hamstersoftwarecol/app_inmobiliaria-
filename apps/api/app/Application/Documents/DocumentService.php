<?php

namespace App\Application\Documents;

use App\Application\Audit\AuditLogger;
use App\Domain\Audit\AuditCategory;
use App\Domain\Deals\Enums\TransactionStatus;
use App\Domain\Documents\Contracts\DocumentScanner;
use App\Domain\Documents\DocumentException;
use App\Domain\Documents\Enums\DocumentCategory;
use App\Domain\Documents\Enums\ScanStatus;
use App\Jobs\ScanDocumentVersion;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Transaction;
use App\Models\User;
use App\Support\AgencyClock;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Private transaction documents: stored on a non-public disk, versioned, checked for malware when a
 * scanner is configured, downloaded only through short-lived signed links, and every link and
 * download is logged.
 */
final class DocumentService
{
    public function __construct(
        private readonly DocumentScanner $scanner,
        private readonly AuditLogger $audit,
    ) {}

    public function upload(User $actor, Transaction $transaction, UploadedFile $file, string $title, DocumentCategory $category, string $visibility): Document
    {
        return DB::transaction(function () use ($actor, $transaction, $file, $title, $category, $visibility) {
            $document = Document::query()->create([
                'transaction_id' => $transaction->id,
                'title' => $title,
                'category' => $category,
                'visibility' => $visibility,
                'created_by' => $actor->id,
                // Late uploads to a finished deal inherit its retention date.
                'retain_until' => $transaction->documents()->whereNotNull('retain_until')->max('retain_until')
                    ?? ($transaction->status === TransactionStatus::Open ? null
                        : AgencyClock::today()->addYears((int) config('hitlease.documents.retention_years', 6))->toDateString()),
            ]);
            $this->storeVersion($actor, $document, $file);
            $this->audit->log(AuditCategory::Data, 'documents.uploaded', $actor, $document, null, ['title' => $title, 'visibility' => $visibility]);

            return $document;
        });
    }

    public function addVersion(User $actor, Document $document, UploadedFile $file): DocumentVersion
    {
        $version = DB::transaction(fn () => $this->storeVersion($actor, $document, $file));
        $this->audit->log(AuditCategory::Data, 'documents.versioned', $actor, $document, null, ['version' => $version->version]);

        return $version;
    }

    public function update(User $actor, Document $document, array $data): Document
    {
        $before = $document->only(['title', 'category', 'visibility']);
        $document->fill($data)->save();
        if ($before['visibility'] !== $document->visibility) {
            $this->audit->log(AuditCategory::Data, 'documents.visibility_changed', $actor, $document, ['visibility' => $before['visibility']], ['visibility' => $document->visibility]);
        }

        return $document;
    }

    public function delete(User $actor, Document $document): void
    {
        $document->delete();
        $this->audit->log(AuditCategory::Data, 'documents.deleted', $actor, $document, ['title' => $document->title]);
    }

    /** Short-lived signed download URL (the link itself is logged). */
    public function downloadLink(User $actor, DocumentVersion $version, ?string $ip, ?string $userAgent): string
    {
        $this->assertDownloadable($version);
        $this->log($version, $actor, 'link_issued', $ip, $userAgent);

        return URL::temporarySignedRoute('documents.download', now()->addMinutes((int) config('hitlease.documents.link_minutes', 5)), [
            'version' => $version->id,
            'u' => $actor->id,
        ]);
    }

    public function stream(DocumentVersion $version, ?int $userId, ?string $ip, ?string $userAgent): StreamedResponse
    {
        $this->assertDownloadable($version);
        $this->log($version, $userId ? User::query()->find($userId) : null, 'download', $ip, $userAgent);

        $response = Storage::disk($version->disk)->download($version->path, $version->original_name, [
            'Content-Type' => $version->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ]);

        return $response;
    }

    public function scan(DocumentVersion $version): void
    {
        $stream = Storage::disk($version->disk)->readStream($version->path);
        try {
            [$status, $detail] = $this->scanner->scan($stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        $version->forceFill(['scan_status' => $status, 'scan_detail' => $detail, 'scanned_at' => now()])->save();
        if ($status === ScanStatus::Infected) {
            $this->audit->log(AuditCategory::Security, 'documents.malware_detected', null, $version->document, null, null, ['version_id' => $version->id, 'signature' => $detail]);
        }
    }

    public function scanRequired(): bool
    {
        return $this->scanner->isConfigured();
    }

    private function assertDownloadable(DocumentVersion $version): void
    {
        if (! $version->scan_status->downloadable($this->scanRequired())) {
            throw DocumentException::notAvailable($version->scan_status->value);
        }
    }

    private function storeVersion(User $actor, Document $document, UploadedFile $file): DocumentVersion
    {
        $disk = (string) config('hitlease.documents.disk', 'local');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $path = $file->storeAs("documents/{$document->transaction_id}", Str::uuid().'.'.$extension, ['disk' => $disk]);
        // Serialize concurrent uploads of the same document (the version number is unique per document).
        Document::query()->whereKey($document->id)->lockForUpdate()->first();
        $next = (int) DocumentVersion::query()->where('document_id', $document->id)->max('version') + 1;

        $version = new DocumentVersion([
            'document_id' => $document->id,
            'version' => $next,
            'disk' => $disk,
            'path' => $path,
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 250, ''),
            'mime_type' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'uploaded_by' => $actor->id,
        ]);
        $version->forceFill(['scan_status' => $this->scanRequired() ? ScanStatus::Pending : ScanStatus::Unscanned])->save();
        $this->log($version, $actor, 'upload', request()?->ip(), request()?->userAgent());

        if ($this->scanRequired()) {
            ScanDocumentVersion::dispatch($version)->afterCommit();
        }

        return $version;
    }

    private function log(DocumentVersion $version, ?User $user, string $action, ?string $ip, ?string $userAgent): void
    {
        DB::table('document_access_logs')->insert([
            'document_version_id' => $version->id,
            'user_id' => $user?->id,
            'action' => $action,
            'ip_address' => $ip,
            'user_agent' => $userAgent ? Str::limit($userAgent, 250, '') : null,
            'created_at' => now(),
        ]);
    }
}
