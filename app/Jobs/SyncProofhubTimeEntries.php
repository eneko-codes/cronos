<?php

namespace App\Jobs;

use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\ProofhubApiCalls;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Class SyncProofhubTimeEntries
 *
 * Synchronizes time entries from ProofHub into the local database,
 * including project and task references and user assignments.
 * Also removes local entries within the sync period that are no longer in ProofHub.
 */
class SyncProofhubTimeEntries extends BaseSyncJob
{
  /**
   * The priority of the job in the queue.
   * Lower numbers indicate higher priority.
   *
   * @var int
   */
  public int $priority = 2;

  /**
   * Date range parameters to limit the scope of the sync.
   */
  protected ?string $startDate;
  protected ?string $endDate;

  /**
   * SyncProofhubTimeEntries constructor.
   *
   * @param ProofhubApiCalls $proofhub An instance of the ProofhubApiCalls service
   * @param string|null $startDate Optional start date in Y-m-d format
   * @param string|null $endDate Optional end date in Y-m-d format
   */
  public function __construct(
    ProofhubApiCalls $proofhub,
    ?string $startDate = null,
    ?string $endDate = null
  ) {
    $this->proofhub = $proofhub;
    // Ensure dates are stored internally for use in deletion logic
    $this->startDate = $startDate ?: now()->subDays(30)->format('Y-m-d');
    $this->endDate = $endDate ?: now()->format('Y-m-d');
  }

  /**
   * Executes the synchronization process.
   *
   * This method performs the following operations:
   * 1. Builds parameters for the ProofHub API request
   * 2. Fetches time entries from ProofHub
   * 3. Processes and syncs each valid time entry, collecting their IDs
   * 4. Removes local time entries within the sync period that are no longer present in ProofHub
   *
   * @return void
   *
   * @throws Exception If any part of the synchronization process fails
   */
  protected function execute(): void
  {
    // Step 1: Build parameters for the API request
    $params = $this->buildRequestParameters();

    // Step 2: Fetch time entries from ProofHub API
    $rawEntries = collect($this->proofhub->getAllTime($params));

    // Step 3: Process each valid time entry and collect synced IDs
    $syncedEntryIds = $this->processTimeEntries($rawEntries);

    // Step 4: Remove obsolete local time entries for the synced period
    $this->removeObsoleteTimeEntries($syncedEntryIds);
  }

  /**
   * Builds parameters for the ProofHub API request.
   *
   * @return array Parameters for the API request
   */
  private function buildRequestParameters(): array
  {
    return [
      'from_date' => $this->startDate,
      'to_date' => $this->endDate,
    ];
  }

  /**
   * Processes time entries from ProofHub and collects their IDs.
   *
   * @param Collection $rawEntries Time entries from ProofHub API
   * @return Collection Collection of synced ProofHub time entry IDs
   */
  private function processTimeEntries(Collection $rawEntries): Collection
  {
    if ($rawEntries->isEmpty()) {
      return collect();
    }

    $syncedIds = collect();

    $rawEntries->each(function ($entry) use ($syncedIds) {
      $processedEntryId = $this->processTimeEntry($entry);
      if ($processedEntryId) {
        $syncedIds->push($processedEntryId);
      }
    });

    return $syncedIds;
  }

  /**
   * Processes a single time entry.
   *
   * @param array $entry Time entry data from ProofHub
   * @return int|null The ProofHub ID of the synced entry, or null if skipped
   */
  private function processTimeEntry(array $entry): ?int
  {
    $proofhubEntryId = data_get($entry, 'id');
    if (!$proofhubEntryId) {
      Log::channel('sync')->info(
        class_basename($this) . ': Skipping time entry - ID missing',
        ['entry_data' => $entry]
      );
      return null;
    }

    // Parse date and validate
    $dateUtc = $this->parseDateUtc($entry);
    if (!$dateUtc) {
      return null; // Skip if date is invalid
    }

    // Parse created_at date
    $createdAtUtc = $this->parseCreatedAtUtc($entry);

    // Find user for this time entry
    $user = $this->findUserForTimeEntry($entry);
    if (!$user) {
      return null; // Skip if user not found or trackable
    }

    // Validate project exists
    $projectId = data_get($entry, 'project.id');
    if (!$projectId || !$this->validateProject($projectId, $entry)) {
      return null; // Skip if project invalid
    }

    // Process task information
    $taskInfo = $this->processTaskInfo($entry, $projectId);

    // Create or update the time entry
    $this->createOrUpdateTimeEntry(
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
   * @param array $entry Time entry data
   * @return Carbon|null Parsed date or null if invalid
   */
  private function parseDateUtc(array $entry): ?Carbon
  {
    $dateUtc = data_get($entry, 'date');
    if (!$dateUtc) {
      Log::channel('sync')->warning(
        class_basename($this) . ': Skipping time entry - Date missing',
        ['time_entry_id' => data_get($entry, 'id')]
      );
      return null;
    }

    try {
      return Carbon::parse($dateUtc)->utc();
    } catch (Exception $e) {
      Log::channel('sync')->error(
        class_basename($this) . ': Skipping time entry - Invalid date format',
        [
          'time_entry_id' => data_get($entry, 'id'),
          'date_value' => $dateUtc,
          'error' => $e->getMessage(),
        ]
      );
      return null;
    }
  }

  /**
   * Parses the created_at date.
   *
   * @param array $entry Time entry data
   * @return Carbon Parsed created_at date or current time
   */
  private function parseCreatedAtUtc(array $entry): Carbon
  {
    $createdAt = data_get($entry, 'created_at');
    try {
      return $createdAt ? Carbon::parse($createdAt)->utc() : now()->utc();
    } catch (Exception $e) {
      Log::channel('sync')->warning(
        class_basename($this) .
          ': Invalid created_at format, using current time',
        [
          'time_entry_id' => data_get($entry, 'id'),
          'created_at_value' => $createdAt,
          'error' => $e->getMessage(),
        ]
      );
      return now()->utc();
    }
  }

  /**
   * Finds the user for a time entry.
   *
   * @param array $entry Time entry data
   * @return User|null The user or null if not found or not trackable
   */
  private function findUserForTimeEntry(array $entry): ?User
  {
    $creatorProofhubId = data_get($entry, 'creator.id');
    if (!$creatorProofhubId) {
      Log::channel('sync')->info(
        class_basename($this) . ': Skipping time entry - creator ID missing',
        ['time_entry_id' => data_get($entry, 'id')]
      );
      return null;
    }

    $user = User::where('proofhub_id', $creatorProofhubId)
      ->trackable() // Apply scope here
      ->first();

    if (!$user) {
      $this->logUserIssue($entry, $creatorProofhubId);
      return null;
    }

    return $user;
  }

  /**
   * Logs issues with finding a user for a time entry.
   *
   * @param array $entry Time entry data
   * @param string|int $creatorProofhubId ProofHub user ID
   * @return void
   */
  private function logUserIssue(array $entry, $creatorProofhubId): void
  {
    // Check if user exists but has do_not_track enabled
    $userExistsButNotTrackable = User::where('proofhub_id', $creatorProofhubId)
      ->notTrackable() // Check specifically for not trackable users
      ->exists();

    if ($userExistsButNotTrackable) {
      Log::channel('sync')->info(
        class_basename($this) .
          ': Skipping time entry - User ' .
          $creatorProofhubId .
          ' has do_not_track enabled',
        ['time_entry_id' => data_get($entry, 'id')]
      );
    } else {
      // If the user doesn't exist at all
      Log::channel('sync')->info(
        class_basename($this) .
          ': Skipping time entry - User ' .
          $creatorProofhubId .
          ' not found in database',
        ['time_entry_id' => data_get($entry, 'id')]
      );
    }
  }

  /**
   * Validates that the project associated with a time entry exists locally.
   *
   * @param string|int $projectId ProofHub project ID
   * @param array $entry Time entry data (for logging context)
   * @return bool True if project exists, false otherwise
   */
  private function validateProject($projectId, array $entry): bool
  {
    $projectExists = Project::where(
      'proofhub_project_id',
      $projectId
    )->exists();

    if (!$projectExists) {
      Log::channel('sync')->info(
        class_basename($this) . ': Skipping time entry - Project not found',
        [
          'time_entry_id' => data_get($entry, 'id'),
          'project_id' => $projectId,
        ]
      );
      return false;
    }
    return true;
  }

  /**
   * Processes task information for a time entry.
   *
   * @param array $entry Time entry data
   * @param string|int $projectId ProofHub project ID
   * @return array Contains 'taskId' (nullable) and 'taskName' (nullable)
   */
  private function processTaskInfo(array $entry, $projectId): array
  {
    $taskId = data_get($entry, 'task.id');
    $taskName = data_get($entry, 'task.title');

    // If task ID exists, ensure the task exists locally or create it
    if ($taskId) {
      Task::firstOrCreate(
        ['proofhub_task_id' => $taskId],
        [
          'proofhub_project_id' => $projectId,
          'name' => $taskName ?: 'Task name missing', // Provide default if name missing
        ]
      );
    }

    return [
      'taskId' => $taskId,
      'taskName' => $taskName,
    ];
  }

  /**
   * Creates or updates a time entry in the local database.
   *
   * @param array $entry Time entry data from ProofHub
   * @param User $user Local user model
   * @param string|int $projectId ProofHub project ID
   * @param Carbon $dateUtc Entry date (UTC)
   * @param Carbon $createdAtUtc Entry creation timestamp (UTC)
   * @param array $taskInfo Array containing 'taskId' and 'taskName'
   * @return void
   */
  private function createOrUpdateTimeEntry(
    array $entry,
    User $user,
    $projectId,
    Carbon $dateUtc,
    Carbon $createdAtUtc,
    array $taskInfo
  ): void {
    // Calculate total seconds from API response
    $hours = data_get($entry, 'logged_hours', 0);
    $minutes = data_get($entry, 'logged_mins', 0);
    $totalSeconds = $hours * 3600 + $minutes * 60;

    TimeEntry::updateOrCreate(
      ['proofhub_time_entry_id' => data_get($entry, 'id')], // Use ProofHub ID as the unique key
      [
        'user_id' => $user->id,
        'proofhub_project_id' => $projectId,
        'proofhub_task_id' => $taskInfo['taskId'], // Nullable
        'status' => data_get($entry, 'status', 'unknown'), // Default status
        'description' => data_get($entry, 'description', ''), // Default description
        'date' => $dateUtc->toDateString(),
        'duration_seconds' => $totalSeconds, // Save calculated total seconds
        'proofhub_created_at' => $createdAtUtc,
      ]
    );
  }

  /**
   * Removes local time entries within the sync date range that no longer exist in ProofHub.
   *
   * @param Collection $syncedEntryIds ProofHub IDs of entries synced in this run
   * @return void
   */
  private function removeObsoleteTimeEntries(Collection $syncedEntryIds): void
  {
    // Define the date range Carbon objects for database query
    $startDate = Carbon::parse($this->startDate)->startOfDay();
    $endDate = Carbon::parse($this->endDate)->endOfDay();

    // Get IDs of all local time entries within the sync date range
    $localEntryIds = TimeEntry::whereBetween('date', [
      $startDate,
      $endDate,
    ])->pluck('proofhub_time_entry_id');

    // Determine which local IDs are not present in the synced IDs from ProofHub
    $idsToDelete = $localEntryIds->diff($syncedEntryIds);

    if ($idsToDelete->isEmpty()) {
      return; // No obsolete entries found
    }

    Log::channel('sync')->info(
      class_basename($this) . ': Deleting obsolete time entries',
      [
        'count' => $idsToDelete->count(),
        'ids' => $idsToDelete->all(),
        'date_range' => [$this->startDate, $this->endDate],
      ]
    );

    // Fetch and delete each obsolete entry individually to trigger model events
    TimeEntry::whereIn('proofhub_time_entry_id', $idsToDelete)
      ->get()
      ->each(function (TimeEntry $entry) {
        try {
          $entry->delete();
        } catch (Exception $e) {
          Log::channel('sync')->error(
            class_basename($this) . ': Failed to delete time entry',
            [
              'time_entry_id' => $entry->proofhub_time_entry_id,
              'error' => $e->getMessage(),
            ]
          );
        }
      });
  }
}
