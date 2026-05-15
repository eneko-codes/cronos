<?php

declare(strict_types=1);

namespace App\Actions\Desktime;

use App\Actions\LinkUserExternalIdentityAction;
use App\DataTransferObjects\Desktime\DesktimeUserDTO;
use App\DataTransferObjects\UnlinkedUser;
use App\Enums\Platform;
use Illuminate\Support\Facades\Log;

/**
 * Action to synchronize DeskTime user data with the local users table.
 *
 * Links a local user to their DeskTime identity via the external identities system.
 * Uses email matching first, then falls back to name similarity matching.
 * Returns an UnlinkedUser if the user cannot be linked, which is aggregated
 * by the sync job and sent as a single notification at the end of the sync cycle.
 */
final class ProcessDesktimeUserAction
{
    public function __construct(
        private readonly LinkUserExternalIdentityAction $linkAction,
    ) {}

    /**
     * Synchronizes a single DeskTime user DTO with the local database.
     *
     * @param  DesktimeUserDTO  $userDto  The DesktimeUserDTO to sync.
     * @return UnlinkedUser|null Returns an UnlinkedUser if the user couldn't be linked, null otherwise
     */
    public function execute(DesktimeUserDTO $userDto): ?UnlinkedUser
    {
        // Validate required ID
        if ($userDto->id === null) {
            Log::warning('Skipping DeskTime user: missing ID');

            return null;
        }

        $externalId = (string) $userDto->id;
        $hasEmail = ! empty($userDto->email) && filter_var($userDto->email, FILTER_VALIDATE_EMAIL);
        $hasName = ! empty($userDto->name);

        // Skip users with missing data: both name AND email are missing
        if (! $hasEmail && ! $hasName) {
            Log::warning('DeskTime user has missing data quality.', [
                'desktime_id' => $externalId,
                'name' => $userDto->name,
                'email' => $userDto->email,
            ]);

            return null;
        }

        // Attempt to link the user
        $result = $this->linkAction->execute(
            platform: Platform::DeskTime,
            externalId: $externalId,
            externalEmail: $hasEmail ? $userDto->email : null,
            externalName: $userDto->name,
        );

        if ($result->hasIdentity()) {
            Log::debug('DeskTime user linked successfully.', [
                'desktime_id' => $externalId,
                'user_id' => $result->identity->user_id,
                'linked_by' => $result->identity->linked_by,
                'was_new_link' => $result->isNewLink(),
            ]);

            return null;
        }

        // No match found - return unlinked user for aggregation
        Log::info('DeskTime user could not be linked.', [
            'desktime_id' => $externalId,
            'name' => $userDto->name,
            'email' => $userDto->email,
        ]);

        return new UnlinkedUser(
            externalId: $externalId,
            externalName: $userDto->name,
            externalEmail: $userDto->email,
        );
    }
}
