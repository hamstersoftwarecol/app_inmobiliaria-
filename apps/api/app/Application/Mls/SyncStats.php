<?php

namespace App\Application\Mls;

/**
 * Counters reported on every sync job (stored in mls_sync_jobs.stats).
 */
final class SyncStats
{
    public int $fetched = 0;

    public int $created = 0;

    public int $updated = 0;

    public int $unchanged = 0;

    public int $published = 0;

    public int $hidden = 0;

    public int $removed = 0;

    public int $errors = 0;

    public int $media = 0;

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
