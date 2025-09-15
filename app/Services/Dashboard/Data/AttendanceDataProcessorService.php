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
            $dayAttendances = $this->findAttendancesForDate($attendances, $dateString);

            if ($dayAttendances->isEmpty()) {
                return null;
            }

            $durationInfo = $this->calculateDurationInfo($dayAttendances);
            $times = $this->getAttendanceTimes($dayAttendances);
            $segments = $this->getSegments($dayAttendances);
            $workLocationInfo = $this->analyzeWorkLocation($dayAttendances);

            // Get overall start and end times
            $start = $dayAttendances->min('clock_in');
            $end = $dayAttendances->max('clock_out');

            return [
                'models' => $dayAttendances,
                'duration' => $durationInfo['formatted'],
                'is_remote' => $workLocationInfo['is_remote'],
                'is_mixed' => $workLocationInfo['is_mixed'],
                'has_office' => $workLocationInfo['has_office'],
                'has_remote' => $workLocationInfo['has_remote'],
                'times' => $times,
                'segments' => $segments,
                'start' => $start ? Carbon::parse($start)->toDateTimeString() : null,
                'end' => $end ? Carbon::parse($end)->toDateTimeString() : null,
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
     * Find all attendance records for a specific date.
     *
     * @param  Collection  $attendances  Collection of UserAttendance models
     * @param  string  $dateString  The date to find attendance for
     * @return Collection Collection of attendance records for the date
     */
    protected function findAttendancesForDate(Collection $attendances, string $dateString): Collection
    {
        $targetDate = Carbon::parse($dateString)->startOfDay();

        return $attendances->filter(function ($record) use ($targetDate) {
            if (! $record->date) {
                return false;
            }
            $recordDate = $record->date instanceof Carbon ? $record->date : Carbon::parse($record->date);

            return $recordDate->startOfDay()->equalTo($targetDate);
        })->sortBy('clock_in')->values();
    }

    /**
     * Calculate duration information for attendance records.
     *
     * @param  Collection  $attendances  Collection of attendance records for a day
     * @return array{minutes: int, formatted: string} Duration information
     */
    protected function calculateDurationInfo(Collection $attendances): array
    {
        $totalSeconds = $attendances->sum('duration_seconds');
        $interval = CarbonInterval::seconds((int) $totalSeconds)->cascade();

        return [
            'minutes' => (int) $interval->totalMinutes,
            'formatted' => $interval->format('%hh %Im'),
        ];
    }

    /**
     * Get attendance times for attendance records.
     *
     * @param  Collection  $attendances  Collection of attendance records for a day
     * @return array<string> Array of start and end times
     */
    protected function getAttendanceTimes(Collection $attendances): array
    {
        $firstClockIn = $attendances->whereNotNull('clock_in')->min('clock_in');
        $lastClockOut = $attendances->whereNotNull('clock_out')->max('clock_out');

        if ($firstClockIn && $lastClockOut) {
            return [
                Carbon::parse($firstClockIn)->format('H:i'),
                Carbon::parse($lastClockOut)->format('H:i'),
            ];
        }

        return [];
    }

    /**
     * Get individual segments for attendance records.
     *
     * @param  Collection  $attendances  Collection of attendance records for a day
     * @return array<array{clock_in: string|null, clock_out: string|null, duration: string}> Array of segments
     */
    protected function getSegments(Collection $attendances): array
    {
        return $attendances->map(function ($attendance) {
            return [
                'clock_in' => $attendance->clock_in ? Carbon::parse($attendance->clock_in)->format('H:i') : null,
                'clock_out' => $attendance->clock_out ? Carbon::parse($attendance->clock_out)->format('H:i') : null,
                'duration' => CarbonInterval::seconds((int) $attendance->duration_seconds)
                    ->cascade()
                    ->format('%hh %Im'),
            ];
        })->toArray();
    }

    /**
     * Analyze work location patterns for a day's attendance records.
     *
     * @param  Collection  $attendances  Collection of attendance records for a day
     * @return array{is_remote: bool, is_mixed: bool, has_office: bool, has_remote: bool} Work location analysis
     */
    protected function analyzeWorkLocation(Collection $attendances): array
    {
        $remoteRecords = $attendances->where('is_remote', true);
        $officeRecords = $attendances->where('is_remote', false);

        $hasRemote = $remoteRecords->isNotEmpty();
        $hasOffice = $officeRecords->isNotEmpty();
        $isMixed = $hasRemote && $hasOffice;

        // For backward compatibility, determine primary work type
        // If mixed, prioritize the type with more total duration
        $isRemote = false;
        if ($isMixed) {
            $remoteDuration = $remoteRecords->sum('duration_seconds');
            $officeDuration = $officeRecords->sum('duration_seconds');
            $isRemote = $remoteDuration >= $officeDuration;
        } elseif ($hasRemote) {
            $isRemote = true;
        }

        return [
            'is_remote' => $isRemote,
            'is_mixed' => $isMixed,
            'has_office' => $hasOffice,
            'has_remote' => $hasRemote,
        ];
    }
}
