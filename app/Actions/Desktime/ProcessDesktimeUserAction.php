<?php

declare(strict_types=1);

namespace App\Actions\Desktime;

use App\Actions\LinkUserExternalIdentityAction;
use App\DataTransferObjects\Desktime\DesktimeUserDTO;
use App\Enums\Platform;
use App\Notifications\UnlinkedPlatformUserNotification;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

/**
 * Action to synchronize DeskTime user data with the local users table.
 *
 * Links a local user to their DeskTime identity via the external identities system.
 * Uses email matching first, then falls back to name similarity matching.
 * Notifies maintainers about unlinked users.
 */
final class ProcessDesktimeUserAction
{
    public function __construct(
        private readonly LinkUserExternalIdentityAction $linkAction,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Synchronizes a single DeskTime user DTO with the local database.
     *
     * @param  DesktimeUserDTO  $userDto  The DesktimeUserDTO to sync.
     */
    public function execute(DesktimeUserDTO $userDto): void
    {
        // Validate required ID
        if ($userDto->id === null) {
            Log::warning('Skipping DeskTime user: missing ID');

            return;
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

            return;
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

            return;
        }

        // No match found - notify maintainers
        Log::info('DeskTime user could not be linked.', [
            'desktime_id' => $externalId,
            'name' => $userDto->name,
            'email' => $userDto->email,
        ]);

        $this->notifyUnlinkedUser($externalId, $userDto->name, $userDto->email);
    }

    /**
     * Notify maintainers about an unlinked platform user.
     */
    private function notifyUnlinkedUser(string $externalId, ?string $name, ?string $email): void
    {
        $notification = new UnlinkedPlatformUserNotification(
            platform: Platform::DeskTime,
            externalId: $externalId,
            externalName: $name,
            externalEmail: $email,
        );

        // Rate limiting is handled by RateLimited middleware in the notification
        $this->notificationService->notifyMaintenanceUsers($notification);
    }
}
