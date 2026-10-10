<?php

namespace App\Domain\Listings\Exceptions;

use App\Domain\Shared\DomainException;

final class ListingException extends DomainException
{
    public static function mlsReadOnly(): self
    {
        return new self('mls_listing_read_only', 'MLS listings are maintained by the MLS feed and cannot be edited here.', 409);
    }

    /**
     * @param  list<string>  $missing
     */
    public static function notPublishable(array $missing): self
    {
        return new self('listing_not_publishable', 'The listing is missing information required for publication.', 422, ['missing' => $missing]);
    }

    public static function mlsPublicationManaged(): self
    {
        return new self('mls_publication_managed', 'Publication of MLS listings follows the provider display rules and cannot be changed manually.', 409);
    }

    public static function mediaLimitReached(int $max): self
    {
        return new self('media_limit_reached', "A listing can have at most {$max} photos.", 422, ['max' => $max]);
    }

    public static function agentOutsideAgency(): self
    {
        return new self('listing_agent_invalid', 'The listing agent must be an active agent of the listing agency.', 422);
    }
}
