<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceService
{
    /**
     * Get attendance data for a specific date.
     */
    public function getAttendanceForDate(Collection $attendances, string $dateString): ?array
    {
        $dayAttendances = $this->findAttendancesForDate($attendances, $dateString);

        if ($dayAttendances->isEmpty()) {
            return null;
        }

        $durationInfo = $this->calculateDurationInfo($dayAttendances);
        $times = $this->getAttendanceTimes($dayAttendances);
        $segments = $this->getSegments($dayAttendances);
        $workLocationInfo = $this->analyzeWorkLocation($dayAttendances);

        $start = $dayAttendances->min('clock_in');
        $end = $dayAttendances->max('clock_out');

        $hasOpenSegment = $dayAttendances->contains(function ($attendance) {
            return (bool) ($attendance->clock_in && ! $attendance->clock_out);
        });

        return [
            'models' => $dayAttendances,
            'duration' => $durationInfo['formatted'],
            'is_remote' => $workLocationInfo['is_remote'],
            'is_mixed' => $workLocationInfo['is_mixed'],
            'has_office' => $workLocationInfo['has_office'],
            'has_remote' => $workLocationInfo['has_remote'],
            'times' => $times,
            'segments' => $segments,
            'has_open_segment' => $hasOpenSegment,
            'start' => $start ? Carbon::parse($start)->toDateTimeString() : null,
            'end' => $end ? Carbon::parse($end)->toDateTimeString() : null,
        ];
    }

    /**
     * Find all attendance records for a specific date.
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
     */
    protected function calculateDurationInfo(Collection $attendances): array
    {
        $totalSeconds = $attendances->sum('duration_seconds');

        if ($totalSeconds <= 0) {
            return [
                'minutes' => 0,
                'formatted' => '',
            ];
        }

        $interval = \Carbon\CarbonInterval::seconds((int) $totalSeconds)->cascade();

        return [
            'minutes' => (int) $interval->totalMinutes,
            'formatted' => $interval->format('%hh %Im'),
        ];
    }

    /**
     * Get attendance times for attendance records.
     */
    protected function getAttendanceTimes(Collection $attendances): array
    {
        $firstClockIn = $attendances->whereNotNull('clock_in')->min('clock_in');
        $lastClockOut = $attendances->whereNotNull('clock_out')->max('clock_out');

        if ($firstClockIn && $lastClockOut) {
            return [
                Carbon::parse($firstClockIn)->setTimezone(config('app.timezone'))->format('H:i'),
                Carbon::parse($lastClockOut)->setTimezone(config('app.timezone'))->format('H:i'),
            ];
        }

        return [];
    }

    /**
     * Get individual segments for attendance records.
     */
    protected function getSegments(Collection $attendances): array
    {
        return $attendances->map(function ($attendance) {
            return [
                'clock_in' => $attendance->clock_in ? Carbon::parse($attendance->clock_in)->setTimezone(config('app.timezone'))->format('H:i') : null,
                'clock_out' => $attendance->clock_out ? Carbon::parse($attendance->clock_out)->setTimezone(config('app.timezone'))->format('H:i') : null,
                'duration' => $attendance->formatted_duration,
            ];
        })->toArray();
    }

    /**
     * Analyze work location patterns for a day's attendance records.
     */
    protected function analyzeWorkLocation(Collection $attendances): array
    {
        $remoteRecords = $attendances->where('is_remote', true);
        $officeRecords = $attendances->where('is_remote', false);

        $hasRemote = $remoteRecords->isNotEmpty();
        $hasOffice = $officeRecords->isNotEmpty();
        $isMixed = $hasRemote && $hasOffice;

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
