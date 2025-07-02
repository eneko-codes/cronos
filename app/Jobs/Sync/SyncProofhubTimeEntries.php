<?php

declare(strict_types=1);

namespace App\Jobs\Sync;

use App\Clients\ProofhubApiClient;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Job to synchronize ProofHub time entry data with the local time_entries table.
 *
 * Ensures the local time entries database reflects the current state of ProofHub, including:
 * - Creating or updating time entries, with project, task, and user references
 * - Removing obsolete time entries within the sync period
 * - Logging the sync process and results
 */
class SyncProofhubTimeEntries extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 2;

    /**
     * Date range parameters to limit the scope of the sync.
     */
    protected ?string $startDate;

    protected ?string $endDate;

    /**
     * Constructs a new SyncProofhubTimeEntries job instance.
     *
     * @param  ProofhubApiClient  $proofhub  The ProofHub API client instance.
     * @param  string|null  $startDate  Optional start date in Y-m-d format.
     * @param  string|null  $endDate  Optional end date in Y-m-d format.
     */
    public function __construct(
        ProofhubApiClient $proofhub,
        ?string $startDate = null,
        ?string $endDate = null
    ) {
        $this->proofhub = $proofhub;
        // Ensure dates are stored internally for use in deletion logic
        $this->startDate = $startDate ?: now()->format('Y-m-d');
        $this->endDate = $endDate ?: now()->format('Y-m-d');
    }

    /**
     * Main entry point for the job's sync logic.
     *
     * Performs the following operations:
     * 1. Loops through all time entries fetched from ProofHub API for the date range
     * 2. Processes each time entry, updating local records
     * 3. Removes local time entries within the sync period whose ProofHub IDs were not found
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    protected function execute(): void
    {
        Log::info('Starting ProofHub time entry sync.', [
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
        ]);

        $params = [
            'from_date' => $this->startDate,
            'to_date' => $this->endDate,
        ];
        $allEntries = $this->proofhub->getAllTime($params);
        $allSyncedProofhubEntryIds = collect();
        foreach ($allEntries as $entry) {
            $processedId = $this->processSingleTimeEntry($entry);
            if ($processedId !== null) {
                $allSyncedProofhubEntryIds->push($processedId);
            }
        }

        // Remove obsolete local time entries for the synced period
        $this->removeObsoleteTimeEntries(
            $allSyncedProofhubEntryIds->unique(),
            false // syncTruncated is not needed with getAllTime
        );

        // Log the actual date range of the data received
        $allEntryDates = TimeEntry::whereIn('proofhub_time_entry_id', $allSyncedProofhubEntryIds->unique())
            ->pluck('date')
            ->filter();
        if ($allEntryDates->isNotEmpty()) {
            $minDate = $allEntryDates->min();
            $maxDate = $allEntryDates->max();
            Log::info('ProofHub API actual data date range', [
                'min_date' => $minDate,
                'max_date' => $maxDate,
                'entry_count' => $allEntryDates->count(),
            ]);
        }

        Log::info('Finished ProofHub time entry sync.', [
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'total_entries_processed' => $allSyncedProofhubEntryIds->count(),
            'unique_entries_found' => $allSyncedProofhubEntryIds->unique()->count(),
        ]);
    }

    /**
     * Processes a single time entry from the API data.
     *
     * Validates data, finds related models, and creates/updates the TimeEntry record.
     *
     * @param  array  $entry  Time entry data from ProofHub.
     * @return int|null The ProofHub ID of the synced entry, or null if skipped.
     */
    private function processSingleTimeEntry(array $entry): ?int
    {
        $proofhubEntryId = data_get($entry, 'id');
        if (! $proofhubEntryId) {
            Log::warning(
                class_basename($this).': Skipping time entry - ID missing',
                ['entry_data' => $entry]
            );

            return null;
        }

        // Parse date and validate
        $dateUtc = $this->parseDateUtc($entry);
        if (! $dateUtc) {
            return null; // Skip if date is invalid
        }

        // Parse created_at date
        $createdAtUtc = $this->parseCreatedAtUtc($entry);

        // Find user for this time entry
        $user = $this->findUserForTimeEntry($entry);
        if (! $user) {
            return null; // Skip if user not found or trackable
        }

        // Validate project exists
        $projectId = data_get($entry, 'project.id');
        if (! $projectId || ! $this->validateProject($projectId, $entry)) {
            return null; // Skip if project invalid
        }

        // Process task information (ensures task exists if ID provided)
        $taskInfo = $this->processTaskInfo($entry, $projectId);

        // Create or update the time entry
        $this->createOrUpdateTimeEntryRecord(
            $entry,
            $user,
            $projectId,
            $dateUtc,
            $createdAtUtc,
            $taskInfo
        );

        return $proofhubEntryId; // Return the ID of the successfully processed entry
    }

    /**
     * Parses and validates the entry date.
     *
     * @param  array  $entry  Time entry data.
     * @return Carbon|null Parsed date or null if invalid.
     */
    private function parseDateUtc(array $entry): ?Carbon
    {
        $dateString = data_get($entry, 'date');
        if (! $dateString) {
            Log::warning(
                class_basename($this).': Skipping time entry - Date missing',
                ['time_entry_id' => data_get($entry, 'id')]
            );

            return null;
        }

        try {
            return Carbon::parse($dateString)->utc();
        } catch (Exception $e) {
            Log::error(
                class_basename($this).': Skipping time entry - Invalid date format',
                [
                    'time_entry_id' => data_get($entry, 'id'),
                    'date_value' => $dateString,
                    'error' => $e->getMessage(),
                ]
            );

            return null;
        }
    }

    /**
     * Parses and validates the created_at date for the entry.
     *
     * @param  array  $entry  Time entry data.
     * @return Carbon Parsed created_at date in UTC.
     */
    private function parseCreatedAtUtc(array $entry): Carbon
    {
        $createdAtString = data_get($entry, 'created_at');
        try {
            // Return current UTC time if created_at is missing or cannot be parsed
            return $createdAtString
              ? Carbon::parse($createdAtString)->utc()
              : now()->utc();
        } catch (Exception $e) {
            Log::warning(
                class_basename($this).
                  ': Invalid created_at format, using current time',
                [
                    'time_entry_id' => data_get($entry, 'id'),
                    'created_at_value' => $createdAtString,
                    'error' => $e->getMessage(),
                ]
            );

            return now()->utc();
        }
    }

    /**
     * Finds the user for a given time entry.
     *
     * @param  array  $entry  Time entry data from ProofHub.
     * @return User|null The local user model, or null if not found or not trackable.
     */
    private function findUserForTimeEntry(array $entry): ?User
    {
        $creatorProofhubId = data_get($entry, 'creator.id');
        if (! $creatorProofhubId) {
            Log::warning(
                class_basename($this).': Skipping time entry - creator ID missing',
                ['time_entry_id' => data_get($entry, 'id')]
            );

            return null;
        }

        // Attempt to find the user and ensure they are trackable
        $user = User::where('proofhub_id', $creatorProofhubId)
            ->trackable() // Apply scope to only get trackable users
            ->first();

        if (! $user) {
            // Log why the user was skipped (either not found or not trackable)
            $this->logUserIssue($entry, $creatorProofhubId);

            return null;
        }

        return $user;
    }

    /**
     * Logs issues related to user lookup for a time entry.
     *
     * @param  array  $entry  Time entry data from ProofHub.
     * @param  mixed  $creatorProofhubId  The ProofHub user ID of the creator.
     */
    private function logUserIssue(array $entry, $creatorProofhubId): void
    {
        // Check if user exists but has do_not_track enabled
        $userExistsButNotTrackable = User::where('proofhub_id', $creatorProofhubId)
            ->notTrackable() // Check specifically for not trackable users
            ->exists();

        if ($userExistsButNotTrackable) {
            Log::info(
                class_basename($this).
                  ': Skipping time entry - User '.
                  $creatorProofhubId.
                  ' has do_not_track enabled',
                ['time_entry_id' => data_get($entry, 'id')]
            );
        } else {
            // If the user doesn't exist at all in the database
            Log::info(
                class_basename($this).
                  ': Skipping time entry - User '.
                  $creatorProofhubId.
                  ' not found in database',
                ['time_entry_id' => data_get($entry, 'id')]
            );
        }
    }

    /**
     * Validates that a project exists locally for the time entry.
     *
     * @param  mixed  $projectId  ProofHub project ID.
     * @param  array  $entry  Time entry data from ProofHub.
     * @return bool Whether the project exists locally.
     */
    private function validateProject($projectId, array $entry): bool
    {
        if (! $projectId) {
            // Should have been caught earlier, but double-check
            Log::warning(
                class_basename($this).': Skipping time entry - Project ID missing',
                ['time_entry_id' => data_get($entry, 'id')]
            );

            return false;
        }

        $projectExists = Project::where(
            'proofhub_project_id',
            $projectId
        )->exists();

        if (! $projectExists) {
            Log::info(
                class_basename($this).
                  ': Skipping time entry - Project not found locally',
                [
                    'time_entry_id' => data_get($entry, 'id'),
                    'proofhub_project_id' => $projectId,
                ]
            );

            return false;
        }

        return true;
    }

    /**
     * Processes task information for a time entry, ensuring the task exists if an ID is provided.
     *
     * @param  array  $entry  Time entry data from ProofHub.
     * @param  mixed  $projectId  ProofHub project ID.
     * @return array Array containing task information for the time entry.
     */
    private function processTaskInfo(array $entry, $projectId): array
    {
        $taskId = data_get($entry, 'task.id');
        $taskName = data_get($entry, 'task.title');

        // If a task ID is present in the time entry data, ensure the task exists locally.
        // This is crucial because time entries might reference tasks synced previously or in parallel.
        if ($taskId) {
            Task::firstOrCreate(
                ['proofhub_task_id' => $taskId],
                [
                    'proofhub_project_id' => $projectId, // Associate with the correct project
                    'name' => $taskName ?: 'Task name missing', // Provide default if name missing
                ]
            );
        }

        return [
            'taskId' => $taskId, // Return the ID (or null)
            'taskName' => $taskName, // Return the name (or null)
        ];
    }

    /**
     * Creates or updates the TimeEntry record in the local database.
     *
     * @param  array  $entry  Time entry data from ProofHub.
     * @param  User  $user  The local user model.
     * @param  mixed  $projectId  ProofHub project ID.
     * @param  Carbon  $dateUtc  The entry date in UTC.
     * @param  Carbon  $createdAtUtc  The created_at date in UTC.
     * @param  array  $taskInfo  Array containing task information.
     */
    private function createOrUpdateTimeEntryRecord(
        array $entry,
        User $user,
        $projectId,
        Carbon $dateUtc,
        Carbon $createdAtUtc,
        array $taskInfo
    ): void {
        // Calculate total seconds from API response (handle potential nulls)
        $hours = (int) data_get($entry, 'logged_hours', 0);
        $minutes = (int) data_get($entry, 'logged_mins', 0);
        $totalSeconds = $hours * 3600 + $minutes * 60;

        TimeEntry::updateOrCreate(
            ['proofhub_time_entry_id' => data_get($entry, 'id')], // Use ProofHub ID as the unique key
            [
                'user_id' => $user->id,
                'proofhub_project_id' => $projectId,
                'proofhub_task_id' => $taskInfo['taskId'], // Can be null
                'status' => data_get($entry, 'status', 'unknown'), // Provide a default status
                'description' => data_get($entry, 'description', ''), // Default to empty string
                'date' => $dateUtc->toDateString(), // Store date part only
                'duration_seconds' => $totalSeconds,
                'proofhub_created_at' => $createdAtUtc, // Store the creation timestamp from ProofHub
            ]
        );
    }

    /**
     * Removes local time entries within the sync period that no longer exist in ProofHub.
     *
     * @param  Collection  $syncedEntryIds  All unique ProofHub time entry IDs found during sync.
     * @param  bool  $syncTruncated  Whether the sync was truncated (affects deletion logic).
     */
    private function removeObsoleteTimeEntries(
        Collection $syncedEntryIds,
        bool $syncTruncated
    ): void {
        // *** SAFETY CHECK: Skip deletion if sync was truncated due to unreliable pagination ***
        if ($syncTruncated) {
            Log::warning(
                'Skipping obsolete time entry deletion because sync was truncated due to unreliable API pagination.'
            );

            return;
        }

        // Define the date range Carbon objects for database query
        $startDate = Carbon::parse($this->startDate)->startOfDay();
        $endDate = Carbon::parse($this->endDate)->endOfDay();

        Log::debug('Checking for obsolete time entries.', [
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'synced_ids_count' => $syncedEntryIds->count(),
        ]);

        // Find local entry IDs within the date range
        $localEntryIdsQuery = TimeEntry::whereBetween('date', [
            $startDate,
            $endDate,
        ])->pluck('proofhub_time_entry_id');

        // Determine which local IDs were NOT present in the list of IDs synced from ProofHub
        // Use whereNotIn for potentially better performance on large ID sets if $syncedEntryIds is large,
        // although diff() is generally fine for moderate numbers.
        $idsToDelete = $localEntryIdsQuery->diff($syncedEntryIds);

        if ($idsToDelete->isEmpty()) {
            Log::info('No obsolete time entries found within the date range.');

            return; // No obsolete entries found
        }

        Log::info(
            class_basename($this).
              ": Deleting {$idsToDelete->count()} obsolete time entries within date range.",
            [
                'ids_to_delete' => $idsToDelete->all(),
                'date_range' => [$this->startDate, $this->endDate],
            ]
        );

        // Fetch and delete each obsolete entry individually to trigger model events
        TimeEntry::whereIn('proofhub_time_entry_id', $idsToDelete)
            ->get()
            ->each(function (TimeEntry $entry): void {
                try {
                    $entry->delete();
                } catch (Exception $e) {
                    Log::error(class_basename($this).': Failed to delete time entry', [
                        'time_entry_id' => $entry->proofhub_time_entry_id,
                        'error' => $e->getMessage(),
                    ]);
                    // Decide if we should continue or rethrow - for now, continue
                }
            });
    }
}
