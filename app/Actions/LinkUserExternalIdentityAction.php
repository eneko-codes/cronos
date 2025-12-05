<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Platform;
use App\Models\User;
use App\Models\UserExternalIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Action to link a user to their external platform identity.
 *
 * Handles both automatic (email-based) and manual linking between local users
 * and their identities on external platforms (Odoo, DeskTime, ProofHub, SystemPin).
 */
final class LinkUserExternalIdentityAction
{
    /**
     * Link a user to an external platform identity.
     *
     * @param  Platform  $platform  The external platform
     * @param  string  $externalId  The user's ID on the external platform
     * @param  string|null  $externalEmail  The user's email on the external platform
     * @param  bool  $isManualLink  Whether this is a manual admin link
     * @return UserExternalIdentity|null The created/updated identity or null if no user found
     */
    public function execute(
        Platform $platform,
        string $externalId,
        ?string $externalEmail = null,
        bool $isManualLink = false,
    ): ?UserExternalIdentity {
        return DB::transaction(function () use ($platform, $externalId, $externalEmail, $isManualLink): ?UserExternalIdentity {
            // First, check if this external ID is already linked to a user
            /** @var UserExternalIdentity|null $existingIdentity */
            $existingIdentity = UserExternalIdentity::query()
                ->where('platform', $platform)
                ->where('external_id', $externalId)
                ->first();

            if ($existingIdentity) {
                // Update the external email if it changed
                if ($externalEmail && $existingIdentity->external_email !== strtolower(trim($externalEmail))) {
                    $existingIdentity->update(['external_email' => strtolower(trim($externalEmail))]);
                }

                return $existingIdentity;
            }

            // Try to find a user to link by email
            $user = $this->findUserByEmail($externalEmail);

            if (! $user) {
                Log::info("No user found to link for {$platform->value} external ID", [
                    'platform' => $platform->value,
                    'external_id' => $externalId,
                    'external_email' => $externalEmail,
                ]);

                return null;
            }

            // Check if user already has an identity for this platform
            /** @var UserExternalIdentity|null $existingPlatformLink */
            $existingPlatformLink = $user->externalIdentities()
                ->where('platform', $platform)
                ->first();

            if ($existingPlatformLink) {
                Log::warning("User already has a different {$platform->value} identity linked", [
                    'user_id' => $user->id,
                    'existing_external_id' => $existingPlatformLink->external_id,
                    'new_external_id' => $externalId,
                ]);

                return $existingPlatformLink;
            }

            // Create the new identity link
            return UserExternalIdentity::create([
                'user_id' => $user->id,
                'platform' => $platform,
                'external_id' => $externalId,
                'external_email' => $externalEmail ? strtolower(trim($externalEmail)) : null,
                'is_manual_link' => $isManualLink,
                'linked_by' => $this->determineLinkedBy($user, $externalEmail, $isManualLink),
            ]);
        });
    }

    /**
     * Find a user by email (normalized to lowercase).
     */
    private function findUserByEmail(?string $email): ?User
    {
        if (! $email) {
            return null;
        }

        $normalizedEmail = strtolower(trim($email));

        return User::where('email', $normalizedEmail)->first();
    }

    /**
     * Determine how the identity was linked.
     */
    private function determineLinkedBy(User $user, ?string $externalEmail, bool $isManualLink): string
    {
        if ($isManualLink) {
            return 'manual';
        }

        if ($externalEmail && strtolower(trim($externalEmail)) === strtolower($user->email)) {
            return 'email';
        }

        return 'unknown';
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
            $user->externalIdentities()->where('platform', $platform)->delete();

            // Also remove any link to this external ID (in case it was linked to another user)
            UserExternalIdentity::where('platform', $platform)
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
