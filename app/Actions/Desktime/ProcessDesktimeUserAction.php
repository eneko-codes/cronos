<?php

declare(strict_types=1);

namespace App\Actions\Desktime;

use App\DataTransferObjects\Desktime\DesktimeEmployeeDTO;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Action to synchronize DeskTime user data with the local users table.
 * This action links a local user to their DeskTime ID via email.
 */
final class ProcessDesktimeUserAction
{
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

        DB::transaction(function () use ($userDto): void {
            $email = strtolower(trim($userDto->email));
            $user = User::where('email', $email)->first();

            if (! $user) {
                // Fallback: try to find user by desktime_id in case email changed in DeskTime
                $user = User::where('desktime_id', $userDto->id)->first();
                if ($user) {
                    Log::warning('DeskTime email mismatch - email controlled by Odoo', [
                        'desktime_id' => $userDto->id,
                        'odoo_email' => $user->email,
                        'desktime_email' => $email,
                    ]);
                }
            }

            if ($user) {
                // The sync only updates the desktime_id. Other user data comes from Odoo.
                $user->update(['desktime_id' => $userDto->id]);
            } else {
                Log::info('Skipping DeskTime user, not found by email or desktime_id.', [
                    'email' => $email,
                    'desktime_id' => $userDto->id,
                ]);
            }
        });
    }
}
