<?php

namespace App\Jobs;

use App\Models\Schedule;
use App\Models\User;
use App\Services\OdooApiCalls;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Class SyncOdooSchedules
 *
 * Synchronizes schedule data from Odoo into local schedules table.
 * This job ensures the local schedules database reflects the current state of Odoo,
 * including schedules, schedule details, and user schedule assignments.
 */
class SyncOdooSchedules extends BaseSyncJob
{
  /**
   * The priority of the job in the queue.
   *
   * @var int
   */
  public int $priority = 2;

  /**
   * SyncOdooSchedules constructor.
   *
   * @param OdooApiCalls $odoo An instance of the OdooApiCalls service.
   */
  public function __construct(OdooApiCalls $odoo)
  {
    $this->odoo = $odoo;
  }

  /**
   * Executes the synchronization process.
   *
   * This method performs the following operations:
   * 1. Fetches all schedule data from Odoo API (schedules, time slots, and employee assignments)
   * 2. Creates or updates local schedules based on Odoo data
   * 3. Logs schedules that exist locally but not in Odoo for historical integrity
   * 4. Synchronizes schedule details (time slots) for each schedule
   * 5. Updates user schedule assignments with historical tracking
   *
   * @throws Exception If any part of the synchronization process fails
   */
  protected function execute(): void
  {
    try {
      // Step 1: Fetch all schedule-related data
      $data = $this->odoo->getAllScheduleData();
      $odooSchedules = collect($data->get('schedules'));
      $odooTimeSlots = collect($data->get('timeSlots'))->groupBy(
        'calendar_id.0'
      );
      $odooEmployees = collect($data->get('employees'));

      // Step 2: Create or update local schedules
      $odooSchedules->each(function ($scheduleData) {
        Schedule::updateOrCreate(
          ['odoo_schedule_id' => $scheduleData['id']],
          [
            'description' => $scheduleData['name'],
            'average_hours_day' => $scheduleData['hours_per_day'] ?? null,
          ]
        );
      });

      // Step 3: Log schedules that exist locally but not in Odoo
      $odooScheduleIds = $odooSchedules->pluck('id');
      $localScheduleIds = Schedule::pluck('odoo_schedule_id');
      $schedulesToLog = $localScheduleIds->diff($odooScheduleIds);

      if ($schedulesToLog->isNotEmpty()) {
        Schedule::whereIn('odoo_schedule_id', $schedulesToLog)
          ->get()
          ->each(function ($schedule) {
            Log::channel('sync')->info(
              'Schedule no longer exists in Odoo but preserved for historical integrity',
              [
                'odoo_schedule_id' => $schedule->odoo_schedule_id,
                'description' => $schedule->description,
                'detected_at' => now()->toDateTimeString(),
              ]
            );
          });
      }

      // Step 4: Synchronize schedule details (time slots)
      $odooSchedules->each(function ($scheduleData) use ($odooTimeSlots) {
        $odooScheduleId = $scheduleData['id'];
        $timezone = $scheduleData['tz'] ?? 'UTC';

        $schedule = Schedule::where(
          'odoo_schedule_id',
          $odooScheduleId
        )->first();
        if (!$schedule) {
          return;
        }

        // Group time slots
        $odooDetails = $odooTimeSlots->get($odooScheduleId, collect());
        $odooDetailsById = $odooDetails->keyBy('id');
        $existingDetails = $schedule
          ->scheduleDetails()
          ->get()
          ->keyBy('odoo_detail_id');

        // Check for duplicate schedule details
        $duplicates = $odooDetails
          ->groupBy(function ($detail) {
            $dayPeriod = isset($detail['day_period'])
              ? Str::lower($detail['day_period'])
              : 'morning';
            return $detail['dayofweek'] . '-' . $dayPeriod;
          })
          ->filter(function ($group) {
            return $group->count() > 1;
          });

        if ($duplicates->isNotEmpty()) {
          Log::channel('sync')->warning(
            "SyncOdooSchedules: Schedule #{$odooScheduleId} ({$scheduleData['name']}) has duplicate details",
            [
              'schedule_id' => $odooScheduleId,
              'duplicates' => $duplicates
                ->map(function ($group) {
                  list($weekday, $dayPeriod) = explode(
                    '-',
                    $group->first()['dayofweek'] .
                      '-' .
                      (isset($group->first()['day_period'])
                        ? Str::lower($group->first()['day_period'])
                        : 'morning')
                  );
                  return [
                    'weekday' => $weekday,
                    'day_period' => $dayPeriod,
                    'count' => $group->count(),
                    'details' => $group->pluck('id')->toArray(),
                  ];
                })
                ->values()
                ->toArray(),
            ]
          );
        }

        // Process schedule details
        $odooDetailIds = $odooDetailsById->keys();
        $existingDetailIds = $existingDetails->keys();

        // Determine changes
        $toInsertIds = $odooDetailIds->diff($existingDetailIds);
        $toUpdateIds = $odooDetailIds->intersect($existingDetailIds);
        $toDeleteIds = $existingDetailIds->diff($odooDetailIds);

        // Process new schedule details
        foreach ($toInsertIds as $idToInsert) {
          $detailData = $odooDetailsById[$idToInsert];
          $schedule->scheduleDetails()->create([
            'odoo_schedule_id' => $odooScheduleId,
            'odoo_detail_id' => $detailData['id'],
            'weekday' => $detailData['dayofweek'],
            'day_period' => $detailData['day_period']
              ? Str::lower($detailData['day_period'])
              : 'morning',
            'start' => $this->formatOdooTime(
              $detailData['hour_from'],
              $timezone
            ),
            'end' => $this->formatOdooTime($detailData['hour_to'], $timezone),
          ]);
        }

        // Update existing schedule details
        foreach ($toUpdateIds as $idToUpdate) {
          $detailData = $odooDetailsById[$idToUpdate];
          $existingDetail = $existingDetails[$idToUpdate];

          $updatedAttributes = [
            'weekday' => $detailData['dayofweek'],
            'day_period' => $detailData['day_period']
              ? Str::lower($detailData['day_period'])
              : 'morning',
            'start' => $this->formatOdooTime(
              $detailData['hour_from'],
              $timezone
            ),
            'end' => $this->formatOdooTime($detailData['hour_to'], $timezone),
          ];

          if ($this->needsUpdate($existingDetail, $updatedAttributes)) {
            $existingDetail->update($updatedAttributes);
          }
        }

        // Delete schedule details that no longer exist in Odoo
        if ($toDeleteIds->isNotEmpty()) {
          $toDeleteIds->each(function ($detailId) use ($existingDetails) {
            $detail = $existingDetails[$detailId] ?? null;
            if ($detail) {
              $detail->delete();
            }
          });
        }
      });

      // Step 5: Update user schedule assignments
      $startOfDay = Carbon::now()->startOfDay();
      $odooEmployees->filter()->each(function ($emp) use ($startOfDay) {
        $odooUserId = $emp['id'];
        $newOdooScheduleId = $emp['resource_calendar_id'][0] ?? null;

        // Skip users marked do_not_track
        $user = User::where('odoo_id', $odooUserId)->trackable()->first();

        if (!$user) {
          return;
        }

        // Get active schedule
        $activeSchedule = $user
          ->userSchedules()
          ->whereNull('effective_until')
          ->first();

        // If no new schedule, close out the old
        if (!$newOdooScheduleId) {
          if ($activeSchedule) {
            $activeSchedule->update(['effective_until' => $startOfDay]);
          }
          return;
        }

        // Ensure schedule exists
        if (
          !Schedule::where('odoo_schedule_id', $newOdooScheduleId)->exists()
        ) {
          return;
        }

        // If it's the same schedule, do nothing
        if (
          $activeSchedule &&
          $activeSchedule->odoo_schedule_id === $newOdooScheduleId
        ) {
          return;
        }

        // Close old schedule
        if ($activeSchedule) {
          $activeSchedule->update(['effective_until' => $startOfDay]);
        }

        // Create new schedule assignment
        $user->userSchedules()->create([
          'odoo_schedule_id' => $newOdooScheduleId,
          'effective_from' => $startOfDay,
          'effective_until' => null,
        ]);
      });
    } catch (Exception $e) {
      // If it's a duplicate schedule issue, mark the job as failed rather than retrying
      if (
        Str::contains($e->getMessage(), 'duplicate') ||
        Str::contains($e->getMessage(), 'Schedule Duplication Error')
      ) {
        $this->fail($e);
      } else {
        // For other exceptions, let Laravel's retry mechanism work
        throw $e;
      }
    }
  }

  /**
   * Determines if a schedule detail needs to be updated based on attribute changes.
   *
   * @param object $existingDetail The existing schedule detail
   * @param array $newAttributes The new attributes to compare against
   * @return bool True if update is needed, false otherwise
   */
  protected function needsUpdate($existingDetail, array $newAttributes): bool
  {
    foreach ($newAttributes as $key => $value) {
      if ($existingDetail->{$key} != $value) {
        return true;
      }
    }
    return false;
  }

  /**
   * Formats a decimal hour value (e.g., 9.5 => 09:30) in UTC "H:i" format.
   *
   * @param float $timeValue The decimal time value from Odoo
   * @param string $timezone The timezone to use for conversion
   * @return string Formatted time in UTC "H:i" format
   */
  protected function formatOdooTime(
    float $timeValue,
    string $timezone = 'UTC'
  ): string {
    $hours = (int) floor($timeValue);
    $minutes = (int) round(($timeValue - $hours) * 60);
    $timeString = sprintf('%02d:%02d', $hours, $minutes);

    return Carbon::createFromFormat('H:i', $timeString, $timezone)
      ->setTimezone('UTC')
      ->format('H:i');
  }
}
