<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Odoo;

use App\Clients\OdooApiClient;
use App\DataTransferObjects\Odoo\OdooScheduleDetailDTO;
use App\DataTransferObjects\Odoo\OdooScheduleDTO;
use App\Enums\RoleType;
use App\Jobs\Sync\BaseSyncJob;
use App\Models\Schedule;
use App\Models\User;
use App\Notifications\DuplicateScheduleWarning;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Job to synchronize Odoo schedule data (resource.calendar) with the local schedules table.
 *
 * Ensures the local schedules database reflects the current state of Odoo, including:
 * - Creating or updating schedules and schedule details (time slots)
 * - Logging and preserving schedules that no longer exist in Odoo
 * - Detecting and notifying about duplicate schedule details
 * - Updating user schedule assignments with historical tracking
 */
class SyncOdooSchedules extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 2;

    /**
     * Constructs a new SyncOdooSchedules job instance.
     *
     * @param  OdooApiClient  $odoo  The Odoo API client instance.
     */
    public function __construct(OdooApiClient $odoo)
    {
        $this->odoo = $odoo;
    }

    /**
     * Main entry point for the job's sync logic.
     *
     * Performs the following operations:
     * 1. Fetches all schedule data from Odoo API (schedules, time slots, and employee assignments)
     * 2. Creates or updates local schedules based on Odoo data
     * 3. Logs schedules that exist locally but not in Odoo for historical integrity
     * 4. Synchronizes schedule details (time slots) for each schedule
     * 5. Updates user schedule assignments with historical tracking
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    protected function execute(): void
    {
        Log::info(class_basename(static::class).' Started', ['job' => class_basename(static::class)]);
        try {
            // Step 1: Fetch all schedules and their time slots from Odoo
            $odooSchedules = $this->odoo->getSchedules();
            $odooTimeSlots = $this->odoo->getScheduleDetails();
            // Group time slots by their calendar_id for efficient lookup per schedule
            $odooTimeSlotsGrouped = $odooTimeSlots->groupBy(fn (OdooScheduleDetailDTO $d) => $d->calendar_id);

            // Step 2: Create or update local schedules based on Odoo data
            $odooSchedules->each(function (OdooScheduleDTO $scheduleData): void {
                // Skip schedules with missing required name/description
                if (empty($scheduleData->name)) {
                    Log::warning(class_basename(static::class).' Skipping schedule with missing required description', [
                        'job' => class_basename(static::class),
                        'entity' => 'schedule',
                        'schedule_data' => $scheduleData,
                    ]);

                    return;
                }
                // Create or update the local schedule record using Odoo's unique ID
                Schedule::updateOrCreate(
                    ['odoo_schedule_id' => $scheduleData->id],
                    [
                        'description' => $scheduleData->name,
                        'average_hours_day' => $scheduleData->hours_per_day ?? null,
                    ]
                );
            });

            // Step 3: Log schedules that exist locally but not in Odoo (for historical integrity)
            $odooScheduleIds = $odooSchedules->pluck('id');
            $localScheduleIds = Schedule::pluck('odoo_schedule_id');
            $schedulesToLog = $localScheduleIds->diff($odooScheduleIds);

            if ($schedulesToLog->isNotEmpty()) {
                // For each local schedule missing in Odoo, log its presence for audit/history
                Schedule::whereIn('odoo_schedule_id', $schedulesToLog)
                    ->get()
                    ->each(function ($schedule): void {
                        Log::info(
                            class_basename($this).
                              ': Schedule no longer exists in Odoo but preserved for historical integrity',
                            [
                                'odoo_schedule_id' => $schedule->odoo_schedule_id,
                                'description' => $schedule->description,
                                'detected_at' => now()->toDateTimeString(),
                            ]
                        );
                    });
            }

            // Step 4: Synchronize schedule details (time slots) for each schedule
            $odooSchedules->each(function (OdooScheduleDTO $scheduleData) use ($odooTimeSlotsGrouped): void {
                $odooScheduleId = $scheduleData->id;
                $timezone = $scheduleData->tz ?? 'UTC';

                // Find the local schedule record for this Odoo schedule
                $schedule = Schedule::where(
                    'odoo_schedule_id',
                    $odooScheduleId
                )->first();
                if (! $schedule) {
                    // If the schedule does not exist locally, skip further processing
                    return;
                }

                // Get all time slots (details) for this schedule from Odoo
                $odooDetails = $odooTimeSlotsGrouped->get($odooScheduleId, collect());
                // Key Odoo details by their unique Odoo detail ID
                $odooDetailsById = $odooDetails->keyBy(fn (OdooScheduleDetailDTO $d) => $d->id);
                // Get all existing local schedule details for this schedule
                $existingDetails = $schedule
                    ->scheduleDetails()
                    ->get()
                    ->keyBy('odoo_detail_id');

                // Check for duplicate schedule details (same dayofweek and day_period)
                $duplicates = $odooDetails
                    ->groupBy(function (OdooScheduleDetailDTO $detail) {
                        // Use 'morning' as default if day_period is missing
                        $dayPeriod = isset($detail->day_period)
                          ? Str::lower($detail->day_period)
                          : 'morning';

                        return $detail->dayofweek.'-'.$dayPeriod;
                    })
                    ->filter(function ($group) {
                        // Only consider groups with more than one entry as duplicates
                        return collect($group)->count() > 1;
                    });

                // Prepare duplicate details for notification/logging
                $duplicatesDetailsForNotification = $duplicates
                    ->map(function ($group) {
                        $group = collect($group);
                        $firstDetail = $group->first();
                        $dayPeriod = isset($firstDetail->day_period)
                            ? Str::lower($firstDetail->day_period)
                            : 'morning';
                        $dayOfWeek = $firstDetail->dayofweek;

                        return [
                            'weekday' => $dayOfWeek,
                            'day_period' => $dayPeriod,
                            'count' => $group->count(),
                            'details' => $group->pluck('id'),
                        ];
                    })
                    ->values(); // Ensures we have a simple array

                if ($duplicates->isNotEmpty()) {
                    // Log the warning as before
                    Log::warning(
                        class_basename($this).
                          ": Schedule #{$odooScheduleId} ({$scheduleData->name}) has duplicate details",
                        [
                            'schedule_id' => $odooScheduleId,
                            'duplicates' => $duplicatesDetailsForNotification->toArray(), // Use prepared data
                        ]
                    );

                    // Send notification to admins, avoiding duplicates using cache
                    $cacheKey = 'duplicate_schedule_warning_'.$odooScheduleId;
                    if (! Cache::has($cacheKey)) {
                        $admins = User::where('user_type', RoleType::Admin)->get();
                        if ($admins->isNotEmpty()) {
                            try {
                                $notification = (new DuplicateScheduleWarning(
                                    $odooScheduleId,
                                    $scheduleData->name,
                                    $duplicatesDetailsForNotification->toArray() // Convert to array before passing
                                ))->afterCommit(); // Ensure the notification is sent after the job db transaction is committed

                                Notification::send($admins, $notification);

                                // Cache ONLY after successful notification send
                                Cache::put($cacheKey, true, now()->addDays(7));
                                Log::debug(class_basename($this).": Sent duplicate schedule warning and cached for schedule ID: {$odooScheduleId}");

                            } catch (Exception $e) {
                                // Log the error if notification sending failed
                                Log::error(class_basename($this).": Failed to send DuplicateScheduleWarning for schedule ID {$odooScheduleId}: ".$e->getMessage(), [
                                    'exception' => $e,
                                ]);
                            }
                        }
                    }
                }

                // --- Synchronize schedule details ---
                // Find which details to insert, update, or delete
                $odooDetailIds = $odooDetailsById->keys();
                $existingDetailIds = $existingDetails->keys();
                $toInsertIds = $odooDetailIds->diff($existingDetailIds); // New details from Odoo
                $toUpdateIds = $odooDetailIds->intersect($existingDetailIds); // Existing details to update
                $toDeleteIds = $existingDetailIds->diff($odooDetailIds); // Local details to delete

                // Insert new schedule details
                $toInsertIds->each(function ($idToInsert) use (
                    $odooDetailsById,
                    $schedule,
                    $odooScheduleId,
                    $timezone
                ): void {
                    $detailData = $odooDetailsById[$idToInsert];
                    // Create a new local schedule detail record from Odoo data
                    $schedule->scheduleDetails()->create([
                        'odoo_schedule_id' => $odooScheduleId,
                        'odoo_detail_id' => $detailData->id,
                        'weekday' => $detailData->dayofweek,
                        'day_period' => $detailData->day_period
                          ? Str::lower($detailData->day_period)
                          : 'morning',
                        'start' => $this->formatOdooTime(
                            $detailData->hour_from,
                            $timezone
                        ),
                        'end' => $this->formatOdooTime($detailData->hour_to, $timezone),
                    ]);
                });

                // Update existing schedule details if any attribute has changed
                $toUpdateIds->each(function ($idToUpdate) use (
                    $odooDetailsById,
                    $existingDetails,
                    $timezone
                ): void {
                    $detailData = $odooDetailsById[$idToUpdate];
                    $existingDetail = $existingDetails[$idToUpdate];

                    $updatedAttributes = [
                        'weekday' => $detailData->dayofweek,
                        'day_period' => $detailData->day_period
                          ? Str::lower($detailData->day_period)
                          : 'morning',
                        'start' => $this->formatOdooTime(
                            $detailData->hour_from,
                            $timezone
                        ),
                        'end' => $this->formatOdooTime($detailData->hour_to, $timezone),
                    ];

                    // Only update if any attribute has changed
                    if ($this->needsUpdate($existingDetail, $updatedAttributes)) {
                        $existingDetail->update($updatedAttributes);
                    }
                });

                // Delete schedule details that no longer exist in Odoo
                if ($toDeleteIds->isNotEmpty()) {
                    $toDeleteIds->each(function ($detailId) use ($existingDetails): void {
                        $detail = $existingDetails[$detailId] ?? null;
                        if ($detail) {
                            $detail->delete();
                        }
                    });
                }
            });

            // Step 5 (User schedule assignments) is now handled in SyncOdooUsers.
        } catch (Exception $e) {
            // If it's a duplicate schedule issue, mark the job as failed rather than retrying
            if (Str::contains($e->getMessage(), 'duplicate key value violates unique constraint')) {
                Log::error(class_basename($this).": Failed due to potential duplicate schedule detail: {$e->getMessage()}");
                $this->fail($e); // Mark as failed
            } else {
                Log::error(class_basename($this).": Synchronization failed: {$e->getMessage()}", ['exception' => $e]);
                throw $e; // Re-throw other exceptions to allow retries
            }
        }
        Log::info(class_basename(static::class).' Finished', ['job' => class_basename(static::class)]);
    }

    /**
     * Determines if an existing schedule detail needs to be updated based on new attributes.
     *
     * Compares each attribute in the new data to the existing detail. Returns true if any differ.
     *
     * @param  mixed  $existingDetail  The existing schedule detail model.
     * @param  array  $newAttributes  The new attributes from Odoo.
     * @return bool True if an update is needed, false otherwise.
     */
    protected function needsUpdate($existingDetail, array $newAttributes): bool
    {
        // Compare each attribute; if any differ, an update is needed
        foreach ($newAttributes as $key => $value) {
            if ($existingDetail->{$key} != $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Formats a float time value from Odoo to a string in the specified timezone.
     *
     * Converts a float (e.g., 8.5) to a time string (e.g., '08:30:00') in the given timezone.
     *
     * @param  float  $timeValue  The time value from Odoo (e.g., 8.5 for 08:30).
     * @param  string  $timezone  The timezone to use for formatting.
     * @return string The formatted time string (e.g., '08:30:00').
     */
    protected function formatOdooTime(
        float $timeValue,
        string $timezone = 'UTC'
    ): string {
        // Odoo time is a float (e.g., 8.5 for 08:30). Convert to hours and minutes.
        $hours = (int) floor($timeValue);
        $minutes = (int) round(($timeValue - $hours) * 60);
        $timeString = sprintf('%02d:%02d', $hours, $minutes);

        // Create a Carbon instance and format as 'H:i' in UTC
        return Carbon::createFromFormat('H:i', $timeString, $timezone)
            ->setTimezone('UTC')
            ->format('H:i');
    }
}
