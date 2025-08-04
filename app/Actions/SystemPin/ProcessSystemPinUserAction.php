<?php

declare(strict_types=1);

namespace App\Actions\SystemPin;

use App\DataTransferObjects\SystemPin\SystemPinUserDTO;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Action to synchronize SystemPin user data with the local users table.
 * This action links a local user to their SystemPin ID via email.
 */
final class ProcessSystemPinUserAction
{
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
                'email' => $userDto->Email,  // Updated to match API field name
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

        DB::transaction(function () use ($userDto): void {
            $email = strtolower(trim($userDto->Email));  // Updated to match API field name
            $user = User::where('email', $email)->first();

            if (! $user) {
                // Fallback: try to find user by systempin_id in case email changed in SystemPin
                $user = User::where('systempin_id', $userDto->id)->first();
                if ($user) {
                    Log::warning('SystemPin email mismatch - email controlled by Odoo', [
                        'systempin_id' => $userDto->id,
                        'odoo_email' => $user->email,
                        'systempin_email' => $email,
                    ]);
                }
            }

            if ($user) {
                // The sync only updates the systempin_id. Other user data comes from Odoo.
                $user->update(['systempin_id' => $userDto->id]);
            } else {
                Log::info('Skipping SystemPin user, not found by email or systempin_id.', [
                    'email' => $email,
                    'systempin_id' => $userDto->id,
                ]);
            }
        });
    }
}
