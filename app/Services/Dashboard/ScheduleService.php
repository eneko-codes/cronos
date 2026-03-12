<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\UserSchedule;
use App\Services\DurationFormatterService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ScheduleService
{
    /**
     * Get the scheduled duration in minutes for a specific date.
     *
     * @param  Collection  $schedules  Collection of UserSchedule models
     * @param  string  $dateString  The date string (Y-m-d)
     * @return int The scheduled duration in minutes, or 0 if no schedule exists
     */
    public function getScheduledDurationForDate(Collection $schedules, string $dateString): int
    {
        $target = Carbon::parse($dateString)->startOfDay();
        $weekday = (int) (($target->dayOfWeek + 6) % 7); // 0=Monday, 6=Sunday (Odoo format)

        // Find the active user schedule record on that date
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
     * Get schedule data for a specific date.
     */
    public function getScheduleForDate(Collection $schedules, string $dateString): ?array
    {
        // Return null if no schedules are provided
        if ($schedules->isEmpty()) {
            return null;
        }

        $schedule = $this->findScheduleForDate($schedules, $dateString);

        if (! $schedule) {
            return null;
        }

        $weekday = (Carbon::parse($dateString)->dayOfWeek + 6) % 7;
        $targetDate = Carbon::parse($dateString)->toDateString();

        $details = $schedule->schedule->scheduleDetails
            ->where('weekday', $weekday)
            ->filter(function ($detail) use ($targetDate) {
                if ($detail->active !== true) {
                    return false;
                }

                $dateFrom = $detail->date_from ? $detail->date_from->toDateString() : null;
                $dateTo = $detail->date_to ? $detail->date_to->toDateString() : null;

                if (! $dateFrom && ! $dateTo) {
                    return true;
                }

                $afterStart = ! $dateFrom || $targetDate >= $dateFrom;
                $beforeEnd = ! $dateTo || $targetDate <= $dateTo;

                return $afterStart && $beforeEnd;
            });

        $selectedDetails = $details->sortBy('start');
        $totalMinutes = 0;
        $slots = [];

        foreach ($selectedDetails as $detail) {
            // Get the raw time values from the database to avoid timezone conversion
            // The times are stored as time(0) without timezone in the database
            // but Laravel's datetime cast applies app timezone conversion
            // We access the original attributes to get the raw time strings
            $startTime = $detail->getAttributes()['start'] ?? $detail->start->format('H:i:s');
            $endTime = $detail->getAttributes()['end'] ?? $detail->end->format('H:i:s');

            // Parse as UTC times for duration calculation
            $start = \Carbon\Carbon::parse($startTime, 'UTC');
            $end = \Carbon\Carbon::parse($endTime, 'UTC');
            $minutesForSlot = $start->diffInMinutes($end);
            $totalMinutes += $minutesForSlot;
            $slots[] = "{$start->format('H:i')} - {$end->format('H:i')}";
        }

        if ($totalMinutes === 0) {
            return null;
        }

        return [
            'model' => $schedule,
            'duration' => DurationFormatterService::fromMinutes((int) round($totalMinutes)),
            'slots' => $slots,
            'scheduleName' => $schedule->schedule->description ?? null,
            'totalMinutes' => (int) round($totalMinutes),
        ];
    }

    /**
     * Find schedule record for a specific date.
     */
    protected function findScheduleForDate(Collection $schedules, string $dateString): ?UserSchedule
    {
        $targetDate = Carbon::parse($dateString)->startOfDay();

        return $schedules->first(function ($record) use ($targetDate) {
            if (! $record->effective_from) {
                return false;
            }
            $from = Carbon::parse($record->effective_from);
            $until = $record->effective_until ? Carbon::parse($record->effective_until) : null;

            return $from->lte($targetDate) && (! $until || $until->gte($targetDate));
        });
    }
}
