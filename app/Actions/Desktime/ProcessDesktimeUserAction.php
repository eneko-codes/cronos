<?php

declare(strict_types=1);

namespace App\Actions\Desktime;

use App\Actions\LinkUserExternalIdentityAction;
use App\DataTransferObjects\Desktime\DesktimeEmployeeDTO;
use App\Enums\Platform;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Action to synchronize DeskTime user data with the local users table.
 * This action links a local user to their DeskTime identity via the external identities system.
 */
final class ProcessDesktimeUserAction
{
    public function __construct(
        private readonly LinkUserExternalIdentityAction $linkAction,
    ) {}

    /**
     * Synchronizes a single DeskTime user DTO with the local database.
     *
     * @param  DesktimeEmployeeDTO  $userDto  The DesktimeEmployeeDTO to sync.
     */
    public function execute(DesktimeEmployeeDTO $userDto): void
    {
        $validator = Validator::make(
            [
                'id' => $userDto->id,
                'email' => $userDto->email,
            ],
            [
                'id' => 'required|integer',
                'email' => 'required|email',
            ],
            [
                'id.required' => 'DeskTime user is missing an ID.',
                'email.required' => 'DeskTime user (ID: '.$userDto->id.') is missing an email.',
                'email.email' => 'DeskTime user (ID: '.$userDto->id.') has an invalid email address.',
            ]
        );

        if ($validator->fails()) {
            Log::warning('Skipping DeskTime user due to validation failure.', [
                'user_id' => $userDto->id,
                'errors' => $validator->errors()->all(),
            ]);

            return;
        }

        $identity = $this->linkAction->execute(
            platform: Platform::DeskTime,
            externalId: (string) $userDto->id,
            externalEmail: $userDto->email,
        );

        if ($identity) {
            Log::debug('DeskTime user linked successfully.', [
                'desktime_id' => $userDto->id,
                'user_id' => $identity->user_id,
                'linked_by' => $identity->linked_by,
            ]);
        }
    }
}
