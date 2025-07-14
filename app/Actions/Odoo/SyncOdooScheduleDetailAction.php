<?php

declare(strict_types=1);

namespace App\Actions\Odoo;

use App\DataTransferObjects\Odoo\OdooScheduleDetailDTO;
use App\Models\ScheduleDetail;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Action to synchronize a single Odoo schedule detail (resource.calendar.attendance)
 * with the local schedule_details table.
 *
 * This action is responsible for:
 * - Creating a new schedule detail record if it doesn't exist.
 * - Updating an existing schedule detail record if it does exist.
 * - Formatting Odoo time values to a consistent format.
 */
final class SyncOdooScheduleDetailAction
{
    /**
     * Synchronizes a single Odoo schedule detail with the local database.
     *
     * This method handles the creation and updating of `ScheduleDetail` records.
     *
     * @param  OdooScheduleDetailDTO  $scheduleDetailDto  The DTO containing Odoo schedule detail data.
     */
    public function execute(
        OdooScheduleDetailDTO $scheduleDetailDto
    ): void {
        // Validation using Laravel Validator
        $validator = Validator::make(
            [
                'id' => $scheduleDetailDto->id,
                'dayofweek' => $scheduleDetailDto->dayofweek,
                'hour_from' => $scheduleDetailDto->hour_from,
                'hour_to' => $scheduleDetailDto->hour_to,
            ],
            [
                'id' => 'required',
                'dayofweek' => 'required',
                'hour_from' => 'required',
                'hour_to' => 'required',
            ]
        );

        if ($validator->fails()) {
            Log::warning(class_basename(self::class).' Skipping schedule detail due to validation errors', [
                'scheduleDetailDto' => $scheduleDetailDto,
                'errors' => $validator->errors()->all(),
            ]);

            return;
        }

        DB::transaction(function () use ($scheduleDetailDto): void {
            // Format Odoo float-based hour values to standard time strings for start and end times
            $start = $this->formatOdooTime($scheduleDetailDto->hour_from);
            $end = $this->formatOdooTime($scheduleDetailDto->hour_to);

            // Create or update the schedule detail record
            ScheduleDetail::updateOrCreate(
                [
                    'odoo_detail_id' => $scheduleDetailDto->id,
                    'odoo_schedule_id' => Arr::get($scheduleDetailDto->calendar_id, 0),
                ],
                [
                    'weekday' => $scheduleDetailDto->dayofweek !== null ? (int) $scheduleDetailDto->dayofweek : null,
                    'day_period' => $scheduleDetailDto->day_period,
                    'week_type' => $scheduleDetailDto->week_type ?? 0,
                    'date_from' => $scheduleDetailDto->date_from,
                    'date_to' => $scheduleDetailDto->date_to,
                    'start' => $start,
                    'end' => $end,
                    'odoo_created_at' => $scheduleDetailDto->create_date,
                    'odoo_updated_at' => $scheduleDetailDto->write_date,
                    'name' => $scheduleDetailDto->name,
                    'active' => (bool) ($scheduleDetailDto->active ?? true),
                ]
            );
        });
    }

    /**
     * Formats an Odoo float-based hour value into a Carbon time string (HH:MM:SS).
     *
     * Odoo stores time as a float (e.g., 9.5 for 9:30 AM). This converts it to a standard time format.
     *
     * @param  float  $hour  The hour value from Odoo (e.g., 9.5).
     * @return string The formatted time string (e.g., '09:30:00').
     */
    private function formatOdooTime(float $hour): string
    {
        $hours = floor($hour);
        $minutes = ($hour - $hours) * 60;

        // Always use UTC as the timezone for Odoo times
        return Carbon::today('UTC')
            ->setTime((int) $hours, (int) $minutes, 0)
            ->format('H:i:s');
    }
}
