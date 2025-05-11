<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Data;

use App\DataTransferObjects\DailyScheduleData;
use App\Exceptions\DataTransferObjectException;
use App\Models\UserSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Service responsible for processing schedule data for the dashboard.
 * This service handles the transformation of raw schedule data into DailyScheduleData DTOs.
 */
class ScheduleDataProcessorService
{
    /**
     * Process schedule data for a specific date.
     *
     * @param  Collection  $schedules  Collection of UserSchedule models
     * @param  string  $dateString  The date to process schedule for (Y-m-d)
     * @return DailyScheduleData The processed schedule data
     *
     * @throws DataTransferObjectException If there's an error processing the schedule data
     */
    public function processScheduleData(Collection $schedules, string $dateString): DailyScheduleData
    {
        try {
            $schedule = $this->findScheduleForDate($schedules, $dateString);

            if (! $schedule) {
                return new DailyScheduleData(
                    duration: '0h 0m',
                    slots: [],
                    scheduleName: null
                );
            }

            $weekday = (Carbon::parse($dateString)->dayOfWeek + 6) % 7;
            $details = $schedule->schedule->scheduleDetails->where('weekday', $weekday);
            $targetHours = $schedule->schedule->average_hours_day ?? 8.0;
            $targetMinutes = $targetHours * 60;

            if ($details->count() > 0) {
                $periodGroups = $details->groupBy('day_period');
                $selectedDetails = collect();
                foreach ($periodGroups as $periodDetails) {
                    if ($periodDetails->count() == 1) {
                        $selectedDetails->push($periodDetails->first());
                    } else {
                        $standardPeriodMins = 240;
                        $closestDetail = $periodDetails
                            ->sortBy(function ($detail) use ($standardPeriodMins) {
                                $start = Carbon::parse($detail->start);
                                $end = Carbon::parse($detail->end);
                                $mins = $start->diffInMinutes($end);

                                return abs($mins - $standardPeriodMins);
                            })
                            ->first();
                        $selectedDetails->push($closestDetail);
                    }
                }
                $selectedDetails = $selectedDetails->sortBy('start');
            } else {
                $selectedDetails = $details->sortBy('start');
            }

            $totalMinutes = 0;
            $slots = [];
            foreach ($selectedDetails as $detail) {
                $start = Carbon::parse($detail->start)->setTimezone('UTC');
                $end = Carbon::parse($detail->end)->setTimezone('UTC');
                $minutesForSlot = $start->diffInMinutes($end);
                $totalMinutes += $minutesForSlot;
                $slots[] = ucfirst($detail->day_period).": {$start->format('H:i')} - {$end->format('H:i')}";
            }

            return new DailyScheduleData(
                duration: $this->formatMinutesToHoursMinutes((int) $totalMinutes),
                slots: $slots,
                scheduleName: $schedule->schedule->description ?? null
            );
        } catch (\Exception $e) {
            Log::error('Error processing schedule data', [
                'date' => $dateString,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new DataTransferObjectException(
                "Failed to process schedule data for date {$dateString}: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Find schedule record for a specific date.
     *
     * @param  Collection  $schedules  Collection of UserSchedule models
     * @param  string  $dateString  The date to find schedule for
     * @return UserSchedule|null The schedule record or null if none exists
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

    /**
     * Format minutes to hours and minutes string.
     *
     * @param  int  $minutes  The minutes to format
     * @return string The formatted duration string
     */
    protected function formatMinutesToHoursMinutes(int $minutes): string
    {
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return "{$hours}h {$remainingMinutes}m";
    }
}
