<?php

declare(strict_types=1);

namespace App\Actions;

use App\DataTransferObjects\UserMatchResultDTO;
use App\Enums\Platform;
use App\Models\User;
use App\Models\UserExternalIdentity;
use App\Services\UserMatchingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Action to link a user to their external platform identity.
 *
 * Handles both automatic (email and name-based) and manual linking between local users
 * and their identities on external platforms (Odoo, DeskTime, ProofHub, SystemPin).
 *
 * Matching strategy:
 * 1. Check if external ID is already linked (update email if needed)
 * 2. Try exact email match (primary or cross-platform)
 * 3. Fall back to name similarity matching (if enabled)
 */
final class LinkUserExternalIdentityAction
{
    public function __construct(
        private readonly UserMatchingService $matchingService,
    ) {}

    /**
     * Link a user to an external platform identity.
     *
     * @param  Platform  $platform  The external platform
     * @param  string  $externalId  The user's ID on the external platform
     * @param  string|null  $externalEmail  The user's email on the external platform
     * @param  string|null  $externalName  The user's full name (for Odoo, DeskTime, SystemPin)
     * @param  string|null  $firstName  The user's first name (for ProofHub)
     * @param  string|null  $lastName  The user's last name (for ProofHub)
     * @param  bool  $isManualLink  Whether this is a manual admin link
     * @return LinkUserExternalIdentityResult Result containing identity (if linked) and match details
     */
    public function execute(
        Platform $platform,
        string $externalId,
        ?string $externalEmail = null,
        ?string $externalName = null,
        ?string $firstName = null,
        ?string $lastName = null,
        bool $isManualLink = false,
    ): LinkUserExternalIdentityResult {
        return DB::transaction(function () use ($platform, $externalId, $externalEmail, $externalName, $firstName, $lastName, $isManualLink): LinkUserExternalIdentityResult {
            // First, check if this external ID is already linked to a user
            /** @var UserExternalIdentity|null $existingIdentity */
            $existingIdentity = UserExternalIdentity::query()
                ->forPlatform($platform)
                ->where('external_id', $externalId)
                ->first();

            if ($existingIdentity) {
                // Update the external email if it changed
                if ($externalEmail && $existingIdentity->external_email !== strtolower(trim($externalEmail))) {
                    $existingIdentity->update(['external_email' => strtolower(trim($externalEmail))]);
                }

                return LinkUserExternalIdentityResult::alreadyLinked($existingIdentity);
            }

            // Use the matching service to find a user
            $matchResult = $this->matchingService->findMatchingUser(
                email: $externalEmail,
                name: $externalName,
                firstName: $firstName,
                lastName: $lastName,
            );

            if (! $matchResult->hasMatch()) {
                Log::info("No user found to link for {$platform->value} external ID", [
                    'platform' => $platform->value,
                    'external_id' => $externalId,
                    'external_email' => $externalEmail,
                    'external_name' => $this->matchingService->normalizeFullName($externalName, $firstName, $lastName),
                    'match_attempted_by' => 'email_and_name',
                ]);

                return LinkUserExternalIdentityResult::noMatch(
                    $externalEmail,
                    $this->matchingService->normalizeFullName($externalName, $firstName, $lastName),
                );
            }

            $user = $matchResult->user;

            // Check if user already has an identity for this platform
            /** @var UserExternalIdentity|null $existingPlatformLink */
            $existingPlatformLink = $user->externalIdentities()
                ->forPlatform($platform)
                ->first();

            if ($existingPlatformLink) {
                Log::warning("User already has a different {$platform->value} identity linked", [
                    'user_id' => $user->id,
                    'existing_external_id' => $existingPlatformLink->external_id,
                    'new_external_id' => $externalId,
                ]);

                return LinkUserExternalIdentityResult::alreadyLinked($existingPlatformLink);
            }

            // Create the new identity link
            $identity = UserExternalIdentity::create([
                'user_id' => $user->id,
                'platform' => $platform,
                'external_id' => $externalId,
                'external_email' => $externalEmail ? strtolower(trim($externalEmail)) : null,
                'is_manual_link' => $isManualLink,
                'linked_by' => $isManualLink ? 'manual' : $matchResult->matchedBy,
            ]);

            Log::info("Linked {$platform->value} user to local user", [
                'platform' => $platform->value,
                'external_id' => $externalId,
                'user_id' => $user->id,
                'linked_by' => $matchResult->matchedBy,
                'confidence' => $matchResult->confidence,
            ]);

            return LinkUserExternalIdentityResult::linked($identity, $matchResult);
        });
    }

    /**
     * Manually link a user to an external identity (admin operation).
     *
     * This method allows an admin to explicitly link a user to an external platform
     * identity, overwriting any existing link for that platform.
     */
    public function manualLink(
        User $user,
        Platform $platform,
        string $externalId,
        ?string $externalEmail = null,
    ): UserExternalIdentity {
        return DB::transaction(function () use ($user, $platform, $externalId, $externalEmail): UserExternalIdentity {
            // Remove any existing link for this platform from this user
            $user->externalIdentities()
                ->forPlatform($platform)
                ->delete();

            // Also remove any link to this external ID (in case it was linked to another user)
            UserExternalIdentity::forPlatform($platform)
                ->where('external_id', $externalId)
                ->delete();

            return UserExternalIdentity::create([
                'user_id' => $user->id,
                'platform' => $platform,
                'external_id' => $externalId,
                'external_email' => $externalEmail ? strtolower(trim($externalEmail)) : null,
                'is_manual_link' => true,
                'linked_by' => 'manual',
            ]);
        });
    }
}

/**
 * Result of a user external identity linking operation.
 */
final readonly class LinkUserExternalIdentityResult
{
    private function __construct(
        public ?UserExternalIdentity $identity,
        public ?UserMatchResultDTO $matchResult,
        public bool $wasLinked,
        public bool $wasAlreadyLinked,
        public ?string $unmatchedEmail,
        public ?string $unmatchedName,
    ) {}

    /**
     * Create result for a newly linked identity.
     */
    public static function linked(UserExternalIdentity $identity, UserMatchResultDTO $matchResult): self
    {
        return new self(
            identity: $identity,
            matchResult: $matchResult,
            wasLinked: true,
            wasAlreadyLinked: false,
            unmatchedEmail: null,
            unmatchedName: null,
        );
    }

    /**
     * Create result when identity was already linked.
     */
    public static function alreadyLinked(UserExternalIdentity $identity): self
    {
        return new self(
            identity: $identity,
            matchResult: null,
            wasLinked: false,
            wasAlreadyLinked: true,
            unmatchedEmail: null,
            unmatchedName: null,
        );
    }

    /**
     * Create result when no user match was found.
     */
    public static function noMatch(?string $email, ?string $name): self
    {
        return new self(
            identity: null,
            matchResult: null,
            wasLinked: false,
            wasAlreadyLinked: false,
            unmatchedEmail: $email,
            unmatchedName: $name,
        );
    }

    /**
     * Check if the operation resulted in a linked identity (new or existing).
     */
    public function hasIdentity(): bool
    {
        return $this->identity !== null;
    }

    /**
     * Check if this was a new link (not already existing).
     */
    public function isNewLink(): bool
    {
        return $this->wasLinked;
    }

    /**
     * Check if no match was found for the external user.
     */
    public function isUnmatched(): bool
    {
        return ! $this->wasLinked && ! $this->wasAlreadyLinked;
    }
}
