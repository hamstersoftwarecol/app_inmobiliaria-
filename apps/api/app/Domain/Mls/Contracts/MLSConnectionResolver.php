<?php

namespace App\Domain\Mls\Contracts;

use App\Domain\Mls\Exceptions\MLSException;
use App\Models\MlsConnection;

interface MLSConnectionResolver
{
    /**
     * Builds the adapter for a stored connection, enforcing licence/compliance gates.
     *
     * @throws MLSException
     */
    public function resolve(MlsConnection $connection): MLSProviderInterface;

    /**
     * @return list<string> registered driver keys
     */
    public function drivers(): array;
}
