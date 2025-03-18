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
use App\Models\Alert;

/**
 * Class SyncOdooSchedules
 *
 * Synchronizes schedule data from Odoo, including schedule details & user assignments,
 * and invalidates the entire cache store upon completion.
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
   * Removed protected OdooApiCalls $odoo;
   */
  public function __construct(OdooApiCalls $odoo)
  {
    $this->odoo = $odoo;
  }

  protected function execute(): void
  {
    try {
      // Fetch all schedule-related data
      $data = $this->odoo->getAllScheduleData();
      $odooSchedules = collect($data->get('schedules'));
      $odooTimeSlots = collect($data->get('timeSlots'))->groupBy('calendar_id.0');
      $odooEmployees = collect($data->get('employees'));

      // Extract Odoo schedule IDs
      $odooScheduleIds = $odooSchedules->pluck('id');

      // Sync schedules
      $this->syncSchedules($odooSchedules);
      $this->deleteMissingSchedules($odooScheduleIds);
      $this->syncScheduleDetails($odooSchedules, $odooTimeSlots);
      $this->syncUserSchedulesHistorically($odooEmployees);
    } catch (Exception $e) {
      // Log the error but don't rethrow for duplicates
      if (str_contains($e->getMessage(), 'duplicate') || str_contains($e->getMessage(), 'Schedule Duplication Error')) {
        Log::warning('SyncOdooSchedules job encountered duplicate schedule error but will not retry: ' . $e->getMessage());
      } else {
        // Rethrow other exceptions for normal retry behavior
        throw $e;
      }
    }
  }

  protected function syncSchedules(Collection $odooSchedules): void
  {
    foreach ($odooSchedules as $scheduleData) {
      Schedule::updateOrCreate(
        ['odoo_schedule_id' => $scheduleData['id']],
        [
          'description' => $scheduleData['name'],
          'average_hours_day' => $scheduleData['hours_per_day'] ?? null,
        ]
      );
    }
  }

  protected function deleteMissingSchedules(Collection $odooScheduleIds): void
  {
    $localScheduleIds = Schedule::pluck('odoo_schedule_id');
    $schedulesToDelete = $localScheduleIds->diff($odooScheduleIds);

    if ($schedulesToDelete->isNotEmpty()) {
      Schedule::whereIn('odoo_schedule_id', $schedulesToDelete)
        ->get()
        ->each(fn($schedule) => $schedule->delete());
    }
  }

  protected function syncScheduleDetails(
    Collection $odooSchedules,
    Collection $odooTimeSlots
  ): void {
    // Store detected duplicate schedule details
    $duplicateErrors = [];

    foreach ($odooSchedules as $scheduleData) {
      $odooScheduleId = $scheduleData['id'];
      $timezone = $scheduleData['tz'] ?? 'UTC';

      $schedule = Schedule::where('odoo_schedule_id', $odooScheduleId)->first();
      if (!$schedule) {
        continue;
      }

      // Group time slots
      $odooDetails = $odooTimeSlots->get($odooScheduleId, collect());
      $odooDetailsById = $odooDetails->keyBy('id');
      $existingDetails = $schedule
        ->scheduleDetails()
        ->get()
        ->keyBy('odoo_detail_id');

      // Check for duplicate schedule details in Odoo data
      $duplicates = $this->detectDuplicateDetails($odooDetails, $schedule);
      
      if ($duplicates->isNotEmpty()) {
        // Record duplicate details for this schedule
        $duplicateErrors[$odooScheduleId] = [
          'schedule_id' => $odooScheduleId,
          'schedule_name' => $scheduleData['name'],
          'duplicates' => $duplicates->toArray(),
          'detected_at' => now()->toDateTimeString(),
        ];
        
        // Log the issue
        Log::warning("Schedule Duplication Error: Schedule #{$odooScheduleId} ({$scheduleData['name']}) has duplicate details", [
          'schedule_id' => $odooScheduleId, 
          'duplicates' => $duplicates->toArray()
        ]);
        
        // NOTE: We continue processing instead of throwing an exception
        // This prevents job retries for duplicate errors
      }

      $odooDetailIds = $odooDetailsById->keys();
      $existingDetailIds = $existingDetails->keys();

      // Determine changes
      $toInsertIds = $odooDetailIds->diff($existingDetailIds);
      $toUpdateIds = $odooDetailIds->intersect($existingDetailIds);
      $toDeleteIds = $existingDetailIds->diff($odooDetailIds);

      // Inserts
      foreach ($toInsertIds as $idToInsert) {
        $detailData = $odooDetailsById[$idToInsert];
        $schedule->scheduleDetails()->create([
          'odoo_schedule_id' => $odooScheduleId,
          'odoo_detail_id' => $detailData['id'],
          'weekday' => $detailData['dayofweek'],
          'day_period' => $detailData['day_period']
            ? strtolower($detailData['day_period'])
            : 'morning',
          'start' => $this->formatOdooTime($detailData['hour_from'], $timezone),
          'end' => $this->formatOdooTime($detailData['hour_to'], $timezone),
        ]);
      }

      // Updates
      foreach ($toUpdateIds as $idToUpdate) {
        $detailData = $odooDetailsById[$idToUpdate];
        $existingDetail = $existingDetails[$idToUpdate];

        $updatedAttributes = [
          'weekday' => $detailData['dayofweek'],
          'day_period' => $detailData['day_period']
            ? strtolower($detailData['day_period'])
            : 'morning',
          'start' => $this->formatOdooTime($detailData['hour_from'], $timezone),
          'end' => $this->formatOdooTime($detailData['hour_to'], $timezone),
        ];

        if ($this->needsUpdate($existingDetail, $updatedAttributes)) {
          $existingDetail->update($updatedAttributes);
        }
      }

      // Deletes
      if ($toDeleteIds->isNotEmpty()) {
        $toDeleteIds->each(function ($detailId) use ($existingDetails) {
          $detail = $existingDetails[$detailId] ?? null;
          if ($detail) {
            $detail->delete();
          }
        });
      }
    }

    // Create alerts for duplicate errors found
    if (!empty($duplicateErrors)) {
      Log::info('Creating alerts for ' . count($duplicateErrors) . ' schedules with duplicates');
      
      foreach ($duplicateErrors as $errorData) {
        // Alert::createScheduleDuplicateAlert now checks for existing alerts
        // and only creates a new one if needed
        Alert::createScheduleDuplicateAlert($errorData);
      }
    }
  }

  /**
   * Detects duplicate schedule details in Odoo data
   * A duplicate is defined as two or more entries with the same weekday and day_period
   * 
   * @param Collection $odooDetails The Odoo schedule details
   * @param Schedule $schedule The schedule these details belong to
   * @return Collection Collection of duplicate details grouped by weekday and day_period
   */
  protected function detectDuplicateDetails(Collection $odooDetails, Schedule $schedule): Collection 
  {
    $duplicates = collect();
    
    // Group details by weekday and day_period
    $groupedDetails = $odooDetails->groupBy(function ($detail) {
      $dayPeriod = isset($detail['day_period']) 
        ? strtolower($detail['day_period']) 
        : 'morning';
      
      return $detail['dayofweek'] . '-' . $dayPeriod;
    });
    
    // Find groups with more than one entry (duplicates)
    foreach ($groupedDetails as $key => $group) {
      if ($group->count() > 1) {
        list($weekday, $dayPeriod) = explode('-', $key);
        
        $duplicates->push([
          'weekday' => $weekday,
          'day_period' => $dayPeriod,
          'count' => $group->count(),
          'details' => $group->pluck('id')->toArray()
        ]);
      }
    }
    
    return $duplicates;
  }

  protected function needsUpdate($existingDetail, array $newAttributes): bool
  {
    foreach ($newAttributes as $key => $value) {
      if ($existingDetail->{$key} != $value) {
        return true;
      }
    }
    return false;
  }

  protected function syncUserSchedulesHistorically(
    Collection $odooEmployees
  ): void {
    // Map employees to schedule
    $userAssignments = $odooEmployees->filter()->mapWithKeys(function ($emp) {
      return [$emp['id'] => $emp['resource_calendar_id'][0] ?? null];
    });
    
    // Get start of the current day in UTC
    $startOfDay = Carbon::now()->startOfDay();

    $userAssignments->each(function ($newOdooScheduleId, $odooUserId) use ($startOfDay) {
      $user = User::where('odoo_id', $odooUserId)
        ->where('do_not_track', false)
        ->first();

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
      if (!Schedule::where('odoo_schedule_id', $newOdooScheduleId)->exists()) {
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
  }

  /**
   * Formats a decimal hour value (e.g., 9.5 => 09:30) in UTC "H:i" format.
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
