<?php

namespace App\Jobs;

use App\Application\Documents\DocumentService;
use App\Domain\Documents\Enums\ScanStatus;
use App\Models\DocumentVersion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ScanDocumentVersion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public DocumentVersion $version)
    {
        $this->onQueue('documents');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(DocumentService $documents): void
    {
        $documents->scan($this->version);
        // Scanner unreachable or erroring: retry with backoff; the file stays non-downloadable.
        if ($this->version->scan_status === ScanStatus::Error && $this->attempts() < $this->tries) {
            $this->release($this->backoff()[$this->attempts() - 1] ?? 1800);
        }
    }
}
