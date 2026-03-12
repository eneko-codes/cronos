<?php

declare(strict_types=1);

namespace App\Actions\SystemPin;

use App\Actions\LinkUserExternalIdentityAction;
use App\DataTransferObjects\SystemPin\SystemPinUserDTO;
use App\DataTransferObjects\UnlinkedUser;
use App\Enums\Platform;
use Illuminate\Support\Facades\Log;

/**
 * Action to synchronize SystemPin user data with the local users table.
 *
 * Links a local user to their SystemPin identity via the external identities system.
 * Uses email matching first, then falls back to name similarity matching.
 * Returns an UnlinkedUser if the user cannot be linked, which is aggregated
 * by the sync job and sent as a single notification at the end of the sync cycle.
 */
final class ProcessSystemPinUserAction
{
    public function __construct(
        private readonly LinkUserExternalIdentityAction $linkAction,
    ) {}

    /**
     * Synchronizes a single SystemPin user DTO with the local database.
     *
     * @param  SystemPinUserDTO  $userDto  The SystemPinUserDTO to sync.
     * @return UnlinkedUser|null Returns an UnlinkedUser if the user couldn't be linked, null otherwise
     */
    public function execute(SystemPinUserDTO $userDto): ?UnlinkedUser
    {
        // Validate required ID
        if ($userDto->id === null) {
            Log::warning('Skipping SystemPin user: missing ID');

            return null;
        }

        $externalId = (string) $userDto->id;
        $hasEmail = ! empty($userDto->Email) && filter_var($userDto->Email, FILTER_VALIDATE_EMAIL);
        $hasName = ! empty($userDto->Nombre);

        // Skip users with missing data: both name AND email are missing
        if (! $hasEmail && ! $hasName) {
            Log::warning('SystemPin user has missing data quality.', [
                'systempin_id' => $externalId,
                'name' => $userDto->Nombre,
                'email' => $userDto->Email,
            ]);

            return null;
        }

        // Attempt to link the user
        $result = $this->linkAction->execute(
            platform: Platform::SystemPin,
            externalId: $externalId,
            externalEmail: $hasEmail ? $userDto->Email : null,
            externalName: $userDto->Nombre,
        );

        if ($result->hasIdentity()) {
            Log::debug('SystemPin user linked successfully.', [
                'systempin_id' => $externalId,
                'user_id' => $result->identity->user_id,
                'linked_by' => $result->identity->linked_by,
                'was_new_link' => $result->isNewLink(),
            ]);

            return null;
        }

        // No match found - return unlinked user for aggregation
        Log::info('SystemPin user could not be linked.', [
            'systempin_id' => $externalId,
            'name' => $userDto->Nombre,
            'email' => $userDto->Email,
        ]);

        return new UnlinkedUser(
            externalId: $externalId,
            externalName: $userDto->Nombre,
            externalEmail: $userDto->Email,
        );
    }
}
