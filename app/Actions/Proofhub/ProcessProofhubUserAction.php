<?php

declare(strict_types=1);

namespace App\Actions\Proofhub;

use App\Actions\LinkUserExternalIdentityAction;
use App\DataTransferObjects\Proofhub\ProofhubUserDTO;
use App\DataTransferObjects\UnlinkedUser;
use App\Enums\Platform;
use Illuminate\Support\Facades\Log;

/**
 * Action to synchronize ProofHub user data with the local users table.
 *
 * Links a local user to their ProofHub identity via the external identities system.
 * Uses email matching first, then falls back to name similarity matching.
 * Returns an UnlinkedUser if the user cannot be linked, which is aggregated
 * by the sync job and sent as a single notification at the end of the sync cycle.
 */
final class ProcessProofhubUserAction
{
    public function __construct(
        private readonly LinkUserExternalIdentityAction $linkAction,
    ) {}

    /**
     * Synchronizes a single ProofHub user DTO with the local database.
     *
     * @param  ProofhubUserDTO  $userDto  The ProofhubUserDTO to sync.
     * @return UnlinkedUser|null Returns an UnlinkedUser if the user couldn't be linked, null otherwise
     */
    public function execute(ProofhubUserDTO $userDto): ?UnlinkedUser
    {
        // Validate required ID
        if ($userDto->id === null) {
            Log::warning('Skipping ProofHub user: missing ID');

            return null;
        }

        $externalId = (string) $userDto->id;
        $hasEmail = ! empty($userDto->email) && filter_var($userDto->email, FILTER_VALIDATE_EMAIL);
        $hasName = ! empty($userDto->first_name) || ! empty($userDto->last_name);
        $fullName = $this->buildFullName($userDto->first_name, $userDto->last_name);

        // Skip users with missing data: both name AND email are missing
        if (! $hasEmail && ! $hasName) {
            Log::warning('ProofHub user has missing data quality.', [
                'proofhub_id' => $externalId,
                'first_name' => $userDto->first_name,
                'last_name' => $userDto->last_name,
                'email' => $userDto->email,
            ]);

            return null;
        }

        // Attempt to link the user
        $result = $this->linkAction->execute(
            platform: Platform::ProofHub,
            externalId: $externalId,
            externalEmail: $hasEmail ? $userDto->email : null,
            externalName: null, // ProofHub uses separate first/last name
            firstName: $userDto->first_name,
            lastName: $userDto->last_name,
        );

        if ($result->hasIdentity()) {
            Log::debug('ProofHub user linked successfully.', [
                'proofhub_id' => $externalId,
                'user_id' => $result->identity->user_id,
                'linked_by' => $result->identity->linked_by,
                'was_new_link' => $result->isNewLink(),
            ]);

            return null;
        }

        // No match found - return unlinked user for aggregation
        Log::info('ProofHub user could not be linked.', [
            'proofhub_id' => $externalId,
            'first_name' => $userDto->first_name,
            'last_name' => $userDto->last_name,
            'email' => $userDto->email,
        ]);

        return new UnlinkedUser(
            externalId: $externalId,
            externalName: $fullName,
            externalEmail: $userDto->email,
        );
    }

    /**
     * Build a full name from first and last name parts.
     */
    private function buildFullName(?string $firstName, ?string $lastName): ?string
    {
        $parts = array_filter([
            $firstName ? trim($firstName) : null,
            $lastName ? trim($lastName) : null,
        ]);

        return empty($parts) ? null : implode(' ', $parts);
    }
}
