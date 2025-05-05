<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Schedule;
use App\Models\User;
use App\Notifications\DuplicateScheduleWarning;
use App\Services\OdooApiService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
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
     */
    public int $priority = 2;

    /**
     * SyncOdooSchedules constructor.
     *
     * @param  OdooApiService  $odoo  An instance of the OdooApiService service.
     */
    public function __construct(OdooApiService $odoo)
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
            // Step 1: Fetch schedules and time slots separately
            $odooSchedules = $this->odoo->getSchedules();
            // Fetch all schedule details (time slots)
            $odooTimeSlots = $this->odoo->getScheduleDetails();
            // Group the fetched time slots by their parent calendar ID
            $odooTimeSlotsGrouped = $odooTimeSlots->groupBy('calendar_id.0');

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

            // Step 4: Synchronize schedule details (time slots)
            $odooSchedules->each(function ($scheduleData) use ($odooTimeSlotsGrouped) {
                $odooScheduleId = $scheduleData['id'];
                $timezone = $scheduleData['tz'] ?? 'UTC';

                $schedule = Schedule::where(
                    'odoo_schedule_id',
                    $odooScheduleId
                )->first();
                if (! $schedule) {
                    return;
                }

                // Get time slots for the current schedule
                $odooDetails = $odooTimeSlotsGrouped->get($odooScheduleId, collect());
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

                        return $detail['dayofweek'].'-'.$dayPeriod;
                    })
                    ->filter(function ($group) {
                        return $group->count() > 1;
                    });

                // Prepare duplicate data for logging/notification regardless of whether it's empty
                $duplicatesDetailsForNotification = $duplicates
                    ->map(function ($group) {
                        $firstDetail = $group->first();
                        $dayPeriod = isset($firstDetail['day_period'])
                            ? Str::lower($firstDetail['day_period'])
                            : 'morning';
                        $dayOfWeek = $firstDetail['dayofweek'];

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
                          ": Schedule #{$odooScheduleId} ({$scheduleData['name']}) has duplicate details",
                        [
                            'schedule_id' => $odooScheduleId,
                            'duplicates' => $duplicatesDetailsForNotification->toArray(), // Use prepared data
                        ]
                    );

                    // Send notification to admins, avoiding duplicates using cache
                    $cacheKey = 'duplicate_schedule_warning_'.$odooScheduleId;
                    if (! Cache::has($cacheKey)) {
                        $admins = User::where('is_admin', true)->get();
                        if ($admins->isNotEmpty()) {
                            try {
                                $notification = (new DuplicateScheduleWarning(
                                    $odooScheduleId,
                                    $scheduleData['name'],
                                    $duplicatesDetailsForNotification->toArray() // Convert to array before passing
                                ))->afterCommit(); // Ensure the notification is sent after the job db transaction is committed

                                Notification::send($admins, $notification);

                                // Cache ONLY after successful notification send
                                Cache::put($cacheKey, true, now()->addHours(24));
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

                // Process schedule details
                $odooDetailIds = $odooDetailsById->keys();
                $existingDetailIds = $existingDetails->keys();

                // Determine changes
                $toInsertIds = $odooDetailIds->diff($existingDetailIds);
                $toUpdateIds = $odooDetailIds->intersect($existingDetailIds);
                $toDeleteIds = $existingDetailIds->diff($odooDetailIds);

                // Process new schedule details
                $toInsertIds->each(function ($idToInsert) use (
                    $odooDetailsById,
                    $schedule,
                    $odooScheduleId,
                    $timezone
                ) {
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
                });

                // Update existing schedule details
                $toUpdateIds->each(function ($idToUpdate) use (
                    $odooDetailsById,
                    $existingDetails,
                    $timezone
                ) {
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
                });

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
    }

    /**
     * Determines if a schedule detail needs to be updated based on attribute changes.
     *
     * @param  object  $existingDetail  The existing schedule detail
     * @param  array  $newAttributes  The new attributes to compare against
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
     * @param  float  $timeValue  The decimal time value from Odoo
     * @param  string  $timezone  The timezone to use for conversion
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
