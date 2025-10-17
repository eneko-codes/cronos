<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\UserSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ScheduleService
{
    /**
     * Get schedule data for a specific date.
     */
    public function getScheduleForDate(Collection $schedules, string $dateString): ?array
    {
        $cacheKey = "schedule_{$schedules->first()->user_id}_{$dateString}";
        
        return Cache::remember($cacheKey, 300, function () use ($schedules, $dateString) {
            $schedule = $this->findScheduleForDate($schedules, $dateString);
        
        if (!$schedule) {
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

                if (!$dateFrom && !$dateTo) {
                    return true;
                }

                $afterStart = !$dateFrom || $targetDate >= $dateFrom;
                $beforeEnd = !$dateTo || $targetDate <= $dateTo;

                return $afterStart && $beforeEnd;
            });

        $selectedDetails = $details->sortBy('start');
        $totalMinutes = 0;
        $slots = [];

        foreach ($selectedDetails as $detail) {
            $start = Carbon::parse($detail->start)->setTimezone('UTC');
            $end = Carbon::parse($detail->end)->setTimezone('UTC');
            $minutesForSlot = $start->diffInMinutes($end);
            $totalMinutes += $minutesForSlot;
            $slots[] = "{$start->format('H:i')} - {$end->format('H:i')}";
        }

        if ($totalMinutes === 0) {
            return null;
        }

            return [
                'model' => $schedule,
                'duration' => \Carbon\CarbonInterval::minutes((int) round($totalMinutes))->cascade()->format('%hh %Im'),
                'slots' => $slots,
                'scheduleName' => $schedule->schedule->description ?? null,
                'totalMinutes' => (int) round($totalMinutes),
            ];
        });
    }

    /**
     * Find schedule record for a specific date.
     */
    protected function findScheduleForDate(Collection $schedules, string $dateString): ?UserSchedule
    {
        $targetDate = Carbon::parse($dateString)->startOfDay();

        return $schedules->first(function ($record) use ($targetDate) {
            if (!$record->effective_from) {
                return false;
            }
            $from = Carbon::parse($record->effective_from);
            $until = $record->effective_until ? Carbon::parse($record->effective_until) : null;

            return $from->lte($targetDate) && (!$until || $until->gte($targetDate));
        });
    }
}
