<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Models\User;

/**
 * Result of a user matching operation.
 *
 * Contains the matched user (if found), how the match was made,
 * and the confidence level of the match.
 */
final readonly class UserMatchResultDTO
{
    /**
     * @param  User|null  $user  The matched user, or null if no match found
     * @param  string  $matchedBy  How the match was made: 'email', 'cross_platform_email', 'name', 'none'
     * @param  float  $confidence  Confidence score (0-100). 100 for exact email match, lower for name similarity
     */
    public function __construct(
        public ?User $user,
        public string $matchedBy,
        public float $confidence,
    ) {}

    /**
     * Check if a user was matched.
     */
    public function hasMatch(): bool
    {
        return $this->user !== null;
    }

    /**
     * Check if this was a high-confidence match (email-based).
     */
    public function isHighConfidence(): bool
    {
        return $this->confidence >= 100.0;
    }

    /**
     * Create a result for no match found.
     */
    public static function noMatch(): self
    {
        return new self(null, 'none', 0.0);
    }

    /**
     * Create a result for an exact email match.
     */
    public static function emailMatch(User $user, string $matchType = 'email'): self
    {
        return new self($user, $matchType, 100.0);
    }

    /**
     * Create a result for a name similarity match.
     */
    public static function nameMatch(User $user, float $similarity): self
    {
        return new self($user, 'name', $similarity);
    }
}
