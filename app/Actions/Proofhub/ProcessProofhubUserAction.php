<?php

declare(strict_types=1);

namespace App\Actions\Proofhub;

use App\Actions\LinkUserExternalIdentityAction;
use App\DataTransferObjects\Proofhub\ProofhubUserDTO;
use App\Enums\Platform;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Action to synchronize ProofHub user data with the local users table.
 * This action links a local user to their ProofHub identity via the external identities system.
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
     */
    public function execute(ProofhubUserDTO $userDto): void
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
                'id.required' => 'ProofHub user is missing an ID.',
                'email.required' => 'ProofHub user (ID: '.$userDto->id.') is missing an email.',
                'email.email' => 'ProofHub user (ID: '.$userDto->id.') has an invalid email address.',
            ]
        );

        if ($validator->fails()) {
            Log::warning('Skipping ProofHub user due to validation failure.', [
                'user_id' => $userDto->id,
                'errors' => $validator->errors()->all(),
            ]);

            return;
        }

        $identity = $this->linkAction->execute(
            platform: Platform::ProofHub,
            externalId: (string) $userDto->id,
            externalEmail: $userDto->email,
        );

        if ($identity) {
            Log::debug('ProofHub user linked successfully.', [
                'proofhub_id' => $userDto->id,
                'user_id' => $identity->user_id,
                'linked_by' => $identity->linked_by,
            ]);
        }
    }
}
