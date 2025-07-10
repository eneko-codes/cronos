<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Data;

use App\Exceptions\DataTransferObjectException;
use App\Models\UserAttendance;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Service responsible for processing attendance data for the dashboard.
 * This service handles the transformation of raw attendance data into DailyAttendanceData DTOs.
 */
class AttendanceDataProcessorService
{
    /**
     * Process attendance data for a specific date.
     *
     * @param  Collection  $attendances  Collection of UserAttendance models
     * @param  string  $dateString  The date to process attendance for (Y-m-d)
     * @return array|null The processed attendance data as an array, or null if no attendance exists
     */
    public function processAttendanceData(Collection $attendances, string $dateString): ?array
    {
        try {
            $attendance = $this->findAttendanceForDate($attendances, $dateString);

            if (! $attendance) {
                return null;
            }

            $durationInfo = $this->calculateDurationInfo($attendance);
            $times = $this->getAttendanceTimes($attendance);
            $start = $attendance->start ? Carbon::parse($attendance->start)->toDateTimeString() : null;
            $end = $attendance->end ? Carbon::parse($attendance->end)->toDateTimeString() : null;

            return [
                'model' => $attendance,
                'duration' => $durationInfo['formatted'],
                'is_remote' => $attendance->is_remote,
                'times' => $times,
                'start' => $start,
                'end' => $end,
            ];
        } catch (\Exception $e) {
            Log::error('Error processing attendance data', [
                'date' => $dateString,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new DataTransferObjectException(
                "Failed to process attendance data for date {$dateString}: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Find attendance record for a specific date.
     *
     * @param  Collection  $attendances  Collection of UserAttendance models
     * @param  string  $dateString  The date to find attendance for
     * @return UserAttendance|null The attendance record or null if none exists
     */
    protected function findAttendanceForDate(Collection $attendances, string $dateString): ?UserAttendance
    {
        $targetDate = Carbon::parse($dateString)->startOfDay();

        return $attendances->first(function ($record) use ($targetDate) {
            if (! $record->date) {
                return false;
            }
            $recordDate = $record->date instanceof Carbon ? $record->date : Carbon::parse($record->date);

            return $recordDate->startOfDay()->equalTo($targetDate);
        });
    }

    /**
     * Calculate duration information for an attendance record.
     *
     * @param  UserAttendance  $attendance  The attendance record
     * @return array{minutes: int, formatted: string} Duration information
     */
    protected function calculateDurationInfo(UserAttendance $attendance): array
    {
        $durationMinutes = 0;

        if ($attendance->is_remote) {
            $durationMinutes = (int) ($attendance->presence_seconds / 60);
        } else {
            if ($attendance->start && $attendance->end) {
                $start = Carbon::parse($attendance->start);
                $end = Carbon::parse($attendance->end);
                $durationMinutes = $start->diffInMinutes($end);
            } else {
                $durationMinutes = (int) ($attendance->presence_seconds / 60);
            }
        }

        return [
            'minutes' => $durationMinutes,
            'formatted' => CarbonInterval::minutes($durationMinutes)->cascade()->format('%hh %dm'),
        ];
    }

    /**
     * Get attendance times for an attendance record.
     *
     * @param  UserAttendance  $attendance  The attendance record
     * @return array<string> Array of attendance times
     */
    protected function getAttendanceTimes(UserAttendance $attendance): array
    {
        if ($attendance->start && $attendance->end) {
            return [
                Carbon::parse($attendance->start)->format('H:i'),
                Carbon::parse($attendance->end)->format('H:i'),
            ];
        }

        return [];
    }
}
