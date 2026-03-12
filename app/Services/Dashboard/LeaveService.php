<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\UserLeave;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LeaveService
{
    /**
     * Get leave data for a specific date.
     */
    public function getLeaveForDate(Collection $leaves, string $dateString, ?Collection $schedules = null): ?array
    {
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
            'duration' => $durationInfo['hours'],
            'durationText' => $durationInfo['text'],
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
    }

    /**
     * Find the active leave for a specific date.
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
     */
    protected function calculateDurationInfo(UserLeave $leave, string $dateString, ?Collection $schedules = null): array
    {
        $dateCarbon = Carbon::parse($dateString);
        $durationText = $this->formatDurationText($leave->duration_days);

        if (! $leave->start_date->isSameDay($leave->end_date)) {
            $durationMinutes = $this->calculateMultiDayActualMinutes($leave, $dateString, $schedules);
        } else {
            if ($schedules && $leave->duration_days >= 1.0) {
                $scheduledMinutes = $this->getScheduledDurationForDate($schedules, $dateString);
                $durationMinutes = $scheduledMinutes > 0 ? $scheduledMinutes : (8 * 60);
            } else {
                $durationMinutes = (int) round($leave->duration_days * 8 * 60);
            }
        }

        return [
            'text' => $durationText,
            'hours' => $durationMinutes > 0 ? \Carbon\CarbonInterval::minutes($durationMinutes)->cascade()->format('%hh %Im') : '',
            'minutes' => $durationMinutes,
        ];
    }

    /**
     * Format duration text based on duration days.
     */
    protected function formatDurationText(float $durationDays): string
    {
        if ($durationDays == 0.5) {
            return 'Half day';
        } elseif ($durationDays == 1) {
            return '1 day';
        } elseif ($durationDays < 1) {
            $hours = $durationDays * 8;

            return round($hours, 1).' hours';
        }

        return \Carbon\CarbonInterval::days((int) $durationDays)->cascade()->forHumans(['parts' => 2]);
    }

    /**
     * Get the scheduled duration for a date.
     */
    protected function getScheduledDurationForDate(Collection $schedules, string $dateString): int
    {
        $target = Carbon::parse($dateString)->startOfDay();
        $weekday = (int) (($target->dayOfWeek + 6) % 7);

        $userSchedule = $schedules->first(function ($record) use ($target) {
            if (! $record->effective_from) {
                return false;
            }
            $from = Carbon::parse($record->effective_from);
            $until = $record->effective_until ? Carbon::parse($record->effective_until) : null;

            return $from->lte($target) && (! $until || $until->gte($target));
        });

        if (! $userSchedule || ! $userSchedule->schedule) {
            return 0;
        }

        $details = $userSchedule->schedule->scheduleDetails
            ->where('weekday', $weekday)
            ->filter(function ($detail) use ($target) {
                if ($detail->active !== true) {
                    return false;
                }

                $dateFrom = $detail->date_from ? $detail->date_from->toDateString() : null;
                $dateTo = $detail->date_to ? $detail->date_to->toDateString() : null;
                $date = $target->toDateString();

                $afterStart = ! $dateFrom || $date >= $dateFrom;
                $beforeEnd = ! $dateTo || $date <= $dateTo;

                return $afterStart && $beforeEnd;
            });

        if ($details->isEmpty()) {
            return 0;
        }

        $total = 0;
        foreach ($details->sortBy('start') as $detail) {
            $start = Carbon::parse($detail->start)->setTimezone('UTC');
            $end = Carbon::parse($detail->end)->setTimezone('UTC');
            $total += $start->diffInMinutes($end);
        }

        return (int) $total;
    }

    /**
     * Calculate actual minutes for a specific day in a multi-day leave.
     */
    protected function calculateMultiDayActualMinutes(UserLeave $leave, string $dateString, ?Collection $schedules): int
    {
        if (! $schedules) {
            return 8 * 60;
        }

        $scheduledMinutes = $this->getScheduledDurationForDate($schedules, $dateString);

        return $scheduledMinutes > 0 ? $scheduledMinutes : 8 * 60;
    }
}
