<?php

declare(strict_types=1);

namespace App\Actions\Odoo;

use App\DataTransferObjects\Odoo\OdooScheduleDTO;
use App\Models\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Action to synchronize a single Odoo schedule (resource.calendar) with the local schedules table.
 *
 * This action is responsible for:
 * - Validating the incoming OdooScheduleDTO
 * - Creating a new schedule record if it doesn\'t exist
 * - Updating an existing schedule record if it does exist
 */
final class ProcessOdooScheduleAction
{
    /**
     * Executes the synchronization for a single Odoo schedule.
     *
     * @param  OdooScheduleDTO  $scheduleDto  The DTO containing Odoo schedule data.
     */
    public function execute(OdooScheduleDTO $scheduleDto): void
    {
        // Basic validation for critical fields
        $validator = Validator::make(
            [
                'id' => $scheduleDto->id,
                'name' => $scheduleDto->name,
            ],
            [
                'id' => 'required',
                'name' => 'required',
            ]
        );

        if ($validator->fails()) {
            Log::warning(class_basename(self::class).' Skipping schedule due to validation errors', [
                'schedule' => $scheduleDto,
                'errors' => $validator->errors()->all(),
            ]);

            return;
        }

        DB::transaction(function () use ($scheduleDto): void {
            // Create or update the schedule record
            Schedule::updateOrCreate(
                ['odoo_schedule_id' => $scheduleDto->id],
                [
                    'description' => $scheduleDto->name,
                    'average_hours_day' => $scheduleDto->hours_per_day,
                    'two_weeks_calendar' => $scheduleDto->two_weeks_calendar ?? false,
                    'two_weeks_explanation' => $scheduleDto->two_weeks_explanation,
                    'flexible_hours' => $scheduleDto->flexible_hours ?? false,
                    'active' => $scheduleDto->active ?? true,
                    'odoo_created_at' => $scheduleDto->create_date,
                    'odoo_updated_at' => $scheduleDto->write_date,
                ]
            );
        });
    }
}
