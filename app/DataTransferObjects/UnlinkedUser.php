<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

/**
 * Data Transfer Object representing an unlinked user from an external platform.
 *
 * Used to aggregate unlinked users during sync cycles before sending
 * a single notification to maintenance users.
 */
readonly class UnlinkedUser
{
    /**
     * @param  string  $externalId  The external user ID that couldn't be linked
     * @param  string|null  $externalName  The name from the platform
     * @param  string|null  $externalEmail  The email from the platform
     */
    public function __construct(
        public string $externalId,
        public ?string $externalName,
        public ?string $externalEmail,
    ) {}
}
