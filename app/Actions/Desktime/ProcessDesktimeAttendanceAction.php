<?php

declare(strict_types=1);

namespace App\Actions\Desktime;

use App\DataTransferObjects\Desktime\DesktimeAttendanceDTO;
use App\Enums\Platform;
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
     * @param  string  $timezone  The DeskTime account timezone for parsing attendance times.
     */
    public function execute(DesktimeAttendanceDTO $attendanceDto, string $timezone): void
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

        DB::transaction(function () use ($attendanceDto, $timezone): void {
            // Primary lookup: by DeskTime external identity
            $user = User::findByExternalId(Platform::DeskTime, (string) $attendanceDto->id);

            // Fallback: try email lookup
            if (! $user) {
                $email = strtolower(trim($attendanceDto->email));
                $user = User::where('email', $email)->first();
            }

            if (! $user) {
                Log::info('Skipping DeskTime attendance, user not found.', [
                    'desktime_id' => $attendanceDto->id,
                    'email' => $attendanceDto->email,
                    'date' => $attendanceDto->date,
                ]);

                return;
            }

            // Case 1: No DeskTime data - Delete any existing remote records for this day
            if ($attendanceDto->desktimeTime === null || $attendanceDto->desktimeTime === 0) {
                UserAttendance::forUser($user->id)
                    ->forDate($attendanceDto->date)
                    ->remote() // Only delete Desktime (remote) records
                    ->delete();

                return;
            }

            // Case 2: There is DeskTime data - Delete existing remote records and create new one
            UserAttendance::forUser($user->id)
                ->forDate($attendanceDto->date)
                ->remote() // Only delete Desktime (remote) records
                ->delete();

            // Create single attendance segment for the Desktime session
            // DeskTime API returns full datetime strings (e.g., "2023-03-16 09:17:00") in company timezone
            // Parse with correct timezone and explicitly convert to UTC for storage
            $clockIn = is_string($attendanceDto->arrived)
                ? \Carbon\Carbon::parse($attendanceDto->arrived, $timezone)->utc()
                : null;
            $clockOut = is_string($attendanceDto->left)
                ? \Carbon\Carbon::parse($attendanceDto->left, $timezone)->utc()
                : null;
            $durationSeconds = $attendanceDto->desktimeTime ?? 0;

            UserAttendance::create([
                'user_id' => $user->id,
                'date' => $attendanceDto->date,
                'clock_in' => $clockIn,
                'clock_out' => $clockOut,
                'duration_seconds' => $durationSeconds,
                'is_remote' => true, // Desktime is always remote work
            ]);
        });
    }
}
