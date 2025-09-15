<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Data;

use App\Exceptions\DataTransferObjectException;
use App\Models\UserLeave;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Service responsible for processing leave data for the dashboard.
 * This service handles the transformation of raw leave data into DailyLeaveData DTOs.
 */
class LeaveDataProcessorService
{
    /**
     * Process leave data for a specific date.
     *
     * @param  Collection  $leaves  Collection of UserLeave models
     * @param  string  $dateString  The date to process leave for (Y-m-d)
     * @param  Collection|null  $schedules  Optional collection of schedules for duration calculation
     * @return array|null The processed leave data as an array, or null if no leave exists
     *
     * @throws DataTransferObjectException If there's an error processing the leave data
     */
    public function processLeaveData(Collection $leaves, string $dateString, ?Collection $schedules = null): ?array
    {
        try {
            $leave = $this->findActiveLeave($leaves, $dateString);
            if (! $leave) {
                return null;
            }

            $contextInfo = $this->getLeaveContext($leave);
            $timeInfo = $this->calculateTimeInfo($leave);
            $durationInfo = $this->calculateDurationInfo($leave, $dateString, $schedules);

            return [
                'model' => $leave,
                'type' => $leave->type,
                'context' => $contextInfo,
                'leaveType' => $leave->leaveType->name ?? '[No Type Set]',
                'duration' => $durationInfo['text'],
                'durationHours' => $durationInfo['hours'],
                'durationDays' => $leave->duration_days,
                'status' => $leave->status ?? 'validate',
                'isHalfDay' => $leave->isHalfDay(),
                'timePeriod' => $leave->isMorningLeave() ? 'morning' : ($leave->isAfternoonLeave() ? 'afternoon' : 'full-day'),
                'timeRange' => $timeInfo['range'],
                'halfDayTime' => $timeInfo['halfDay'],
                'startTime' => $timeInfo['start'],
                'endTime' => $timeInfo['end'],
                'actualMinutes' => $durationInfo['minutes'],
                'leaveTypeDescription' => null,
            ];
        } catch (\Exception $e) {
            Log::error('Error processing leave data', [
                'date' => $dateString,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new DataTransferObjectException(
                "Failed to process leave data for date {$dateString}: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Find the active leave for a specific date.
     *
     * @param  Collection  $leaves  Collection of UserLeave models
     * @param  string  $dateString  The date to find leave for
     * @return UserLeave|null The active leave or null if none exists
     */
    protected function findActiveLeave(Collection $leaves, string $dateString): ?UserLeave
    {
        $dayCarbon = Carbon::parse($dateString)->startOfDay();

        return $leaves->first(function ($leave) use ($dayCarbon) {
            $leaveStartDate = $leave->start_date->copy()->startOfDay();
            $leaveEndDate = $leave->end_date->copy()->startOfDay();

            return $leaveStartDate->lte($dayCarbon) && $leaveEndDate->gte($dayCarbon);
        });
    }

    /**
     * Get the context information for a leave.
     *
     * @param  UserLeave  $leave  The leave model
     * @return string The context information
     */
    protected function getLeaveContext(UserLeave $leave): string
    {
        return match ($leave->type) {
            'department' => $leave->department->name ?? '',
            'category' => $leave->category->name ?? '',
            default => '',
        };
    }

    /**
     * Calculate time-related information for a leave.
     *
     * @param  UserLeave  $leave  The leave model
     * @return array{range: string, halfDay: string|null, start: string, end: string} Time information
     */
    protected function calculateTimeInfo(UserLeave $leave): array
    {
        $startTime = $leave->start_date->format('H:i');
        $endTime = $leave->end_date->format('H:i');

        return [
            'range' => "{$startTime} - {$endTime}",
            'halfDay' => $leave->getFormattedHalfDayHours(),
            'start' => $startTime,
            'end' => $endTime,
        ];
    }

    /**
     * Calculate duration information for a leave.
     *
     * @param  UserLeave  $leave  The leave model
     * @param  string  $dateString  The date string
     * @param  Collection|null  $schedules  Optional schedules collection
     * @return array{text: string, hours: string, minutes: int} Duration information
     */
    protected function calculateDurationInfo(UserLeave $leave, string $dateString, ?Collection $schedules = null): array
    {
        $dateCarbon = Carbon::parse($dateString);
        $isWeekend = $dateCarbon->isWeekend();
        $durationMinutes = $this->calculateDurationMinutes($leave, $dateString, $schedules, $isWeekend);
        $durationText = $this->formatDurationText($leave->duration_days);

        return [
            'text' => $durationText,
            'hours' => CarbonInterval::minutes($durationMinutes)->cascade()->format('%hh %Im'),
            'minutes' => $durationMinutes,
        ];
    }

    /**
     * Calculate the duration in minutes for a leave.
     *
     * @param  UserLeave  $leave  The leave model
     * @param  string  $dateString  The date string
     * @param  Collection|null  $schedules  Optional schedules collection
     * @param  bool  $isWeekend  Whether the date is a weekend
     * @return int The duration in minutes
     */
    protected function calculateDurationMinutes(UserLeave $leave, string $dateString, ?Collection $schedules, bool $isWeekend): int
    {
        if ($leave->start_date->isSameDay($leave->end_date) && $leave->duration_days < 1) {
            return $this->calculateSingleDayDuration($leave, $dateString, $schedules, $isWeekend);
        }

        return $this->calculateMultiDayDuration($leave, $isWeekend);
    }

    /**
     * Format duration text based on duration days.
     *
     * @param  float  $durationDays  The duration in days
     * @return string The formatted duration text
     */
    protected function formatDurationText(float $durationDays): string
    {
        if ($durationDays == 0.5) {
            return 'Half day';
        } elseif ($durationDays == 1) {
            return '1 day';
        }

        return CarbonInterval::days($durationDays)->cascade()->forHumans(['parts' => 2]);
    }

    /**
     * Calculate duration for a single day leave.
     *
     * @param  UserLeave  $leave  The leave model
     * @param  string  $dateString  The date string
     * @param  Collection|null  $schedules  Optional schedules collection
     * @param  bool  $isWeekend  Whether the date is a weekend
     * @return int The duration in minutes
     */
    protected function calculateSingleDayDuration(UserLeave $leave, string $dateString, ?Collection $schedules, bool $isWeekend): int
    {
        if ($schedules !== null) {
            $scheduledMinutes = $this->getScheduledDurationForDate($schedules, $dateString);
            if ($scheduledMinutes == 0 && ! $isWeekend) {
                $scheduledMinutes = 8 * 60;
            }

            return $scheduledMinutes;
        }

        return ! $isWeekend ? 8 * 60 : 0;
    }

    /**
     * Calculate duration for a multi-day leave.
     *
     * @param  UserLeave  $leave  The leave model
     * @param  bool  $isWeekend  Whether the date is a weekend
     * @return int The duration in minutes
     */
    protected function calculateMultiDayDuration(UserLeave $leave, bool $isWeekend): int
    {
        if (($leave->status === 'validate' || $leave->status === 'confirm') && $leave->duration_days > 0) {
            if ($leave->duration_days >= 1) {
                return 8 * 60;
            }

            return (int) round($leave->duration_days * 8 * 60);
        }

        return 0;
    }

    /**
     * Get the scheduled duration for a date.
     *
     * @param  Collection  $schedules  Collection of schedules
     * @param  string  $dateString  The date string
     * @return int The scheduled duration in minutes
     */
    protected function getScheduledDurationForDate(Collection $schedules, string $dateString): int
    {
        // This method would need to be implemented based on your schedule structure
        // For now, returning a default value
        return 8 * 60;
    }
}
