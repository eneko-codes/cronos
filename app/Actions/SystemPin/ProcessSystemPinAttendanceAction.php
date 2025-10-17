<?php

declare(strict_types=1);

namespace App\Actions\SystemPin;

use App\DataTransferObjects\SystemPin\SystemPinAttendanceDTO;
use App\Models\User;
use App\Models\UserAttendance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Action to synchronize SystemPin attendance data with the local user_attendances table.
 * SystemPin attendance is always marked as on-site (is_remote = false) since it's a physical attendance machine.
 */
final class ProcessSystemPinAttendanceAction
{
    /**
     * Synchronizes a single SystemPin attendance DTO with the local database.
     *
     * @param  SystemPinAttendanceDTO  $attendanceDto  The SystemPinAttendanceDTO to sync.
     */
    public function execute(SystemPinAttendanceDTO $attendanceDto): void
    {
        $validator = Validator::make(
            [
                'InternalEmployeeID' => $attendanceDto->InternalEmployeeID,
                'Date' => $attendanceDto->Date,
            ],
            [
                'InternalEmployeeID' => 'required|integer',
                'Date' => 'required|string',
            ],
            [
                'InternalEmployeeID.required' => 'SystemPin attendance is missing an InternalEmployeeID.',
                'Date.required' => 'SystemPin attendance (InternalEmployeeID: '.$attendanceDto->InternalEmployeeID.') is missing a date.',
            ]
        );

        if ($validator->fails()) {
            Log::warning('Skipping SystemPin attendance due to validation failure.', [
                'InternalEmployeeID' => $attendanceDto->InternalEmployeeID,
                'Date' => $attendanceDto->Date,
                'errors' => $validator->errors()->all(),
            ]);

            return;
        }

        DB::transaction(function () use ($attendanceDto): void {
            // Find user by SystemPin ID
            $user = User::where('systempin_id', $attendanceDto->InternalEmployeeID)->first();

            if (! $user) {
                Log::info('Skipping SystemPin attendance, user not found by systempin_id.', [
                    'systempin_id' => $attendanceDto->InternalEmployeeID,
                    'date' => $attendanceDto->Date,
                ]);

                return;
            }

            // Convert SystemPin date format (YYYYMMDD) to Y-m-d
            $date = \Carbon\Carbon::createFromFormat('Ymd', $attendanceDto->Date)->format('Y-m-d');

            // Process TimeRecords to calculate attendance
            $timeRecords = $attendanceDto->TimeRecords ?? [];

            // If no time records, delete any existing record for this day
            if (empty($timeRecords)) {
                UserAttendance::where('user_id', $user->id)
                    ->whereDate('date', $date)
                    ->where('is_remote', false) // Only delete SystemPin records
                    ->delete();

                return;
            }

            // Delete existing attendance records for this day to ensure clean data
            UserAttendance::where('user_id', $user->id)
                ->whereDate('date', $date)
                ->where('is_remote', false) // Only delete SystemPin records
                ->delete();

            // Create one row per time segment
            foreach ($timeRecords as $record) {
                $clockIn = null;
                $clockOut = null;
                $durationSeconds = 0;

                if (isset($record['From'])) {
                    $clockIn = $this->parseTime($this->parseSystemPinDateTime($record['From']), $date);
                }

                if (isset($record['From'], $record['to'])) {
                    // Complete segment with both in and out
                    // Parse as configured timezone (local office time) and convert to UTC
                    $timezone = config('services.systempin.timezone', 'Europe/Madrid');
                    $start = \Carbon\Carbon::createFromFormat('YmdHis', $record['From'], $timezone)->utc();
                    $end = \Carbon\Carbon::createFromFormat('YmdHis', $record['to'], $timezone)->utc();
                    $durationSeconds = $start->diffInSeconds($end);
                    $clockOut = $this->parseTime($this->parseSystemPinDateTime($record['to']), $date);
                }

                // Create attendance record for this segment
                // Carbon instances are already converted to UTC above
                UserAttendance::create([
                    'user_id' => $user->id,
                    'date' => $date,
                    'clock_in' => $clockIn,
                    'clock_out' => $clockOut,
                    'duration_seconds' => $durationSeconds,
                    'is_remote' => false, // SystemPin is always on-site
                ]);
            }
        });
    }

    /**
     * Parse a time string and combine it with a date to create a full datetime.
     * SystemPin returns times in local office timezone (configurable).
     * Parse with timezone and let Laravel convert to UTC for storage.
     *
     * @param  string|null  $timeString  Time in format like "08:30" or "0830"
     * @param  string  $date  Date in Y-m-d format
     * @return \Carbon\Carbon|null Full Carbon datetime or null if parsing fails
     */
    private function parseTime(?string $timeString, string $date): ?\Carbon\Carbon
    {
        if (empty($timeString)) {
            return null;
        }

        try {
            // Handle different time formats that SystemPin might return
            $cleanTime = str_replace(':', '', $timeString);

            if (strlen($cleanTime) === 4) {
                // Format: HHMM
                $hours = substr($cleanTime, 0, 2);
                $minutes = substr($cleanTime, 2, 2);
                $timeFormatted = $hours.':'.$minutes.':00';
            } elseif (strlen($cleanTime) === 6) {
                // Format: HHMMSS
                $hours = substr($cleanTime, 0, 2);
                $minutes = substr($cleanTime, 2, 2);
                $seconds = substr($cleanTime, 4, 2);
                $timeFormatted = $hours.':'.$minutes.':'.$seconds;
            } else {
                // Try to parse as-is
                $timeFormatted = $timeString;
            }

            // Parse as configured timezone (local office time) and explicitly convert to UTC
            $timezone = config('services.systempin.timezone', 'Europe/Madrid');
            return Carbon::parse($date.' '.$timeFormatted, $timezone)->utc();
        } catch (\Exception $e) {
            Log::warning('Failed to parse SystemPin time', [
                'time_string' => $timeString,
                'date' => $date,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Parses SystemPin datetime format (YYYYMMDDHHMMSS) to HH:MM format.
     *
     * @param  string  $systemPinDateTime  DateTime in SystemPin format (e.g., "20250804090300")
     * @return string|null Time in HH:MM format or null if parsing fails
     */
    private function parseSystemPinDateTime(string $systemPinDateTime): ?string
    {
        try {
            // SystemPin format: YYYYMMDDHHMMSS (14 characters)
            if (strlen($systemPinDateTime) !== 14) {
                return null;
            }

            $hours = substr($systemPinDateTime, 8, 2);
            $minutes = substr($systemPinDateTime, 10, 2);

            return $hours.':'.$minutes;
        } catch (\Exception $e) {
            Log::warning('Failed to parse SystemPin datetime', [
                'datetime' => $systemPinDateTime,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
