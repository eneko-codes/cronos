<?php

declare(strict_types=1);

namespace App\Actions\SystemPin;

use App\Actions\LinkUserExternalIdentityAction;
use App\DataTransferObjects\SystemPin\SystemPinUserDTO;
use App\Enums\Platform;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Action to synchronize SystemPin user data with the local users table.
 * This action links a local user to their SystemPin identity via the external identities system.
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
     */
    public function execute(SystemPinUserDTO $userDto): void
    {
        $validator = Validator::make(
            [
                'id' => $userDto->id,
                'email' => $userDto->Email,
            ],
            [
                'id' => 'required|integer',
                'email' => 'required|email',
            ],
            [
                'id.required' => 'SystemPin user is missing an ID.',
                'email.required' => 'SystemPin user (ID: '.$userDto->id.') is missing an email.',
                'email.email' => 'SystemPin user (ID: '.$userDto->id.') has an invalid email address.',
            ]
        );

        if ($validator->fails()) {
            Log::warning('Skipping SystemPin user due to validation failure.', [
                'user_id' => $userDto->id,
                'errors' => $validator->errors()->all(),
            ]);

            return;
        }

        $identity = $this->linkAction->execute(
            platform: Platform::SystemPin,
            externalId: (string) $userDto->id,
            externalEmail: $userDto->Email,
        );

        if ($identity) {
            Log::debug('SystemPin user linked successfully.', [
                'systempin_id' => $userDto->id,
                'user_id' => $identity->user_id,
                'linked_by' => $identity->linked_by,
            ]);
        }
    }
}
