<?php

namespace App\Domain\Search\Exceptions;

use App\Domain\Shared\DomainException;

final class SearchException extends DomainException
{
    public static function favoritesLimit(int $max): self
    {
        return new self('favorites_limit_reached', "You can save up to {$max} favorites. Remove some to add more.", 422, ['max' => $max]);
    }

    public static function savedSearchLimit(int $max): self
    {
        return new self('saved_search_limit_reached', "You can keep up to {$max} saved searches.", 422, ['max' => $max]);
    }
}
