<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Data;

use App\Exceptions\DataTransferObjectException;
use App\Models\UserSchedule;
use Carbon\Carbon;
use Carbon\CarbonInterval;
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
     * @return array|null The processed schedule data as an array, or null if no schedule exists
     */
    public function processScheduleData(Collection $schedules, string $dateString): ?array
    {
        try {
            $schedule = $this->findScheduleForDate($schedules, $dateString);

            if (! $schedule) {
                return null;
            }

            $weekday = (Carbon::parse($dateString)->dayOfWeek + 6) % 7;
            $targetDate = Carbon::parse($dateString)->toDateString();

            // Filter schedule details to only include explicitly active ones for the target date
            $details = $schedule->schedule->scheduleDetails
                ->where('weekday', $weekday)
                ->filter(function ($detail) use ($targetDate) {
                    // Check if the detail is active
                    if ($detail->active !== true) {
                        return false;
                    }

                    // Check if the detail applies to the target date
                    $dateFrom = $detail->date_from ? $detail->date_from->toDateString() : null;
                    $dateTo = $detail->date_to ? $detail->date_to->toDateString() : null;

                    // If no date range specified, it applies to all dates
                    if (! $dateFrom && ! $dateTo) {
                        return true;
                    }

                    // Check if target date is within the range
                    $afterStart = ! $dateFrom || $targetDate >= $dateFrom;
                    $beforeEnd = ! $dateTo || $targetDate <= $dateTo;

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
                $slots[] = ucfirst($detail->day_period).": {$start->format('H:i')} - {$end->format('H:i')}";
            }

            return [
                'model' => $schedule,
                'duration' => CarbonInterval::minutes((int) round($totalMinutes))->cascade()->format('%hh %dm'),
                'slots' => $slots,
                'scheduleName' => $schedule->schedule->description ?? null,
            ];
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
}
