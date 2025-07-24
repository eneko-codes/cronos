<?php

declare(strict_types=1);

namespace App\Actions\Proofhub;

use App\DataTransferObjects\Proofhub\ProofhubUserDTO;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Action to synchronize ProofHub user data with the local users table.
 * This action links a local user to their ProofHub ID via email.
 */
class ProcessProofhubUserAction
{
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

        DB::transaction(function () use ($userDto): void {
            $user = User::where('email', $userDto->email)->first();

            if ($user) {
                // The sync only updates the proofhub_id. Other user data comes from Odoo.
                $user->update(['proofhub_id' => $userDto->id]);
            } else {
                Log::info('Skipping ProofHub user, not found by email.', ['email' => $userDto->email]);
            }
        });
    }
}
