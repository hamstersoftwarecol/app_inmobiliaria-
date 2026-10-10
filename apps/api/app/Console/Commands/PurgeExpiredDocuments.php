<?php

namespace App\Console\Commands;

use App\Application\Audit\AuditLogger;
use App\Domain\Audit\AuditCategory;
use App\Models\Document;
use App\Support\AgencyClock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes transaction documents whose retention period has ended (set when the deal closes or is
 * cancelled). Dry run unless --force: retention must be confirmed with counsel before enabling.
 */
class PurgeExpiredDocuments extends Command
{
    protected $signature = 'hitlease:documents:purge {--force : Actually delete files and records}';

    protected $description = 'List (or with --force delete) documents past their retention date';

    public function handle(AuditLogger $audit): int
    {
        $today = AgencyClock::today()->toDateString();
        $query = Document::withTrashed()->whereNotNull('retain_until')->where('retain_until', '<', $today)->with('versions');
        $count = 0;

        $query->chunkById(100, function ($documents) use (&$count, $audit) {
            foreach ($documents as $document) {
                $count++;
                $this->line("#{$document->id} {$document->title} (retain until {$document->retain_until->toDateString()}, {$document->versions->count()} version(s))");
                if (! $this->option('force')) {
                    continue;
                }
                foreach ($document->versions as $version) {
                    Storage::disk($version->disk)->delete($version->path);
                }
                $audit->log(AuditCategory::Data, 'documents.purged', null, $document, ['title' => $document->title, 'versions' => $document->versions->count()]);
                $document->forceDelete();
            }
        });

        $this->info($this->option('force') ? "Purged {$count} document(s)." : "{$count} document(s) past retention (dry run; use --force to delete).");

        return self::SUCCESS;
    }
}
