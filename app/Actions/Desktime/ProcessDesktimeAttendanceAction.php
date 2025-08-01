<?php

declare(strict_types=1);

namespace App\Actions\Desktime;

use App\DataTransferObjects\Desktime\DesktimeAttendanceDTO;
use App\Models\User;
use App\Models\UserAttendance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Action to synchronize DeskTime attendance data with the local user_attendances table.
 */
final class ProcessDesktimeAttendanceAction
{
    /**
     * Synchronizes a single DeskTime attendance DTO with the local database.
     *
     * @param  DesktimeAttendanceDTO  $attendanceDto  The DesktimeAttendanceDTO to sync.
     */
    public function execute(DesktimeAttendanceDTO $attendanceDto): void
    {
        $validator = Validator::make(
            [
                'id' => $attendanceDto->id,
                'email' => $attendanceDto->email,
                'date' => $attendanceDto->date,
            ],
            [
                'id' => 'required|integer',
                'email' => 'required|email',
                'date' => 'required|date',
            ],
            [
                'id.required' => 'DeskTime attendance is missing a user ID.',
                'email.required' => 'DeskTime attendance (ID: '.$attendanceDto->id.') is missing an email.',
                'email.email' => 'DeskTime attendance (ID: '.$attendanceDto->id.') has an invalid email address.',
                'date.required' => 'DeskTime attendance (ID: '.$attendanceDto->id.') is missing a date.',
            ]
        );

        if ($validator->fails()) {
            Log::warning('Skipping DeskTime attendance due to validation failure.', [
                'user_id' => $attendanceDto->id,
                'date' => $attendanceDto->date,
                'errors' => $validator->errors()->all(),
            ]);

            return;
        }

        DB::transaction(function () use ($attendanceDto): void {
            $email = strtolower(trim($attendanceDto->email));
            $user = User::where('email', $email)->first();

            if (! $user) {
                Log::info('Skipping DeskTime attendance, user not found by email.', [
                    'email' => $email,
                    'date' => $attendanceDto->date,
                ]);

                return;
            }

            // Case 1: No DeskTime data - Delete any existing record for this day
            if ($attendanceDto->desktimeTime === null || $attendanceDto->desktimeTime === 0) {
                UserAttendance::where('user_id', $user->id)
                    ->whereDate('date', $attendanceDto->date)
                    ->delete();

                return;
            }

            // Case 2: There is DeskTime data - Create or update record
            $start = is_string($attendanceDto->arrived) ? $attendanceDto->arrived : null;
            $end = is_string($attendanceDto->left) ? $attendanceDto->left : null;

            UserAttendance::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'date' => $attendanceDto->date,
                ],
                [
                    'presence_seconds' => $attendanceDto->desktimeTime ?? 0,
                    'start' => $start,
                    'end' => $end,
                    'is_remote' => true,
                ]
            );
        });
    }
}
