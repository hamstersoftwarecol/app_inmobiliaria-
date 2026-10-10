<?php

namespace App\Jobs\Mls;

/**
 * Detects listings removed from the feed and hides them (with a safety threshold).
 */
class ReconcileMLSListings extends RunsMlsSync {}
