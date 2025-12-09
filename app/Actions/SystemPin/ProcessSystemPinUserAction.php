<?php

declare(strict_types=1);

namespace App\Actions\SystemPin;

use App\Actions\LinkUserExternalIdentityAction;
use App\DataTransferObjects\SystemPin\SystemPinUserDTO;
use App\Enums\Platform;
use App\Notifications\UnlinkedPlatformUserNotification;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

/**
 * Action to synchronize SystemPin user data with the local users table.
 *
 * Links a local user to their SystemPin identity via the external identities system.
 * Uses email matching first, then falls back to name similarity matching.
 * Notifies maintainers about unlinked users.
 */
final class ProcessSystemPinUserAction
{
    public function __construct(
        private readonly LinkUserExternalIdentityAction $linkAction,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Synchronizes a single SystemPin user DTO with the local database.
     *
     * @param  SystemPinUserDTO  $userDto  The SystemPinUserDTO to sync.
     */
    public function execute(SystemPinUserDTO $userDto): void
    {
        // Validate required ID
        if ($userDto->id === null) {
            Log::warning('Skipping SystemPin user: missing ID');

            return;
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

            return;
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

            return;
        }

        // No match found - notify maintainers
        Log::info('SystemPin user could not be linked.', [
            'systempin_id' => $externalId,
            'name' => $userDto->Nombre,
            'email' => $userDto->Email,
        ]);

        $this->notifyUnlinkedUser($externalId, $userDto->Nombre, $userDto->Email);
    }

    /**
     * Notify maintainers about an unlinked platform user.
     */
    private function notifyUnlinkedUser(string $externalId, ?string $name, ?string $email): void
    {
        $notification = new UnlinkedPlatformUserNotification(
            platform: Platform::SystemPin,
            externalId: $externalId,
            externalName: $name,
            externalEmail: $email,
        );

        // Rate limiting is handled by RateLimited middleware in the notification
        $this->notificationService->notifyMaintenanceUsers($notification);
    }
}
