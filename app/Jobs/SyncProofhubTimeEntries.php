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
    $this->startDate = $startDate ?: now()->format('Y-m-d');
    $this->endDate = $endDate ?: now()->format('Y-m-d');
  }

  /**
   * Executes the synchronization process page by page.
   *
   * This method performs the following operations:
   * 1. Loops through pages of time entries fetched from ProofHub API using callPage.
   * 2. Processes time entries from each page.
   * 3. Collects all valid ProofHub time entry IDs encountered.
   * 4. Removes local time entries within the sync period whose ProofHub IDs were not found.
   *
   * @return void
   *
   * @throws Exception If any part of the synchronization process fails
   */
  protected function execute(): void
  {
    $endpoint = 'alltime';
    $allSyncedProofhubEntryIds = collect(); // Track all synced entry IDs
    $currentPage = 1;
    $totalPages = 1; // Initialize for fallback
    $nextPageUrl = null;
    $syncTruncated = false; // Flag to track if sync stopped early due to API issue

    $baseUrl = config('services.proofhub.company_url');
    if (!$baseUrl) {
      throw new Exception('ProofHub company URL not configured.');
    }
    $initialUrl = "https://{$baseUrl}.proofhub.com/api/v3/{$endpoint}";

    // Initial parameters including date range
    $initialParams = $this->buildRequestParameters();

    Log::channel('sync')->info('Starting ProofHub time entry sync.', [
      'start_date' => $this->startDate,
      'end_date' => $this->endDate,
    ]);

    do {
      // Determine URL and params for the API call
      $urlToCall = $nextPageUrl ?: $initialUrl;
      $paramsToCall = [];
      if ($nextPageUrl === null && $currentPage === 1) {
        // First request using fallback
        $paramsToCall = $initialParams;
        $paramsToCall['page'] = $currentPage;
        $urlToCall = "https://{$baseUrl}.proofhub.com/api/v3/{$endpoint}"; // Ensure base URL
      } elseif ($nextPageUrl === null) {
        // Subsequent fallback requests - IMPORTANT: Re-include initial params
        $paramsToCall = $initialParams; // Start with original filters
        $paramsToCall['page'] = $currentPage; // Add the current page
        $urlToCall = "https://{$baseUrl}.proofhub.com/api/v3/{$endpoint}"; // Ensure base URL
      }

      // Call API for the current page
      $pageResult = $this->proofhub->callPage(
        $urlToCall,
        $paramsToCall,
        $endpoint
      );
      $entriesOnPage = $pageResult['data'];
      $nextPageUrl = $pageResult['nextPageUrl'];
      $totalPagesFromHeader = $pageResult['totalPages'];

      // Check for empty page (after first page, using fallback)
      if (
        $entriesOnPage->isEmpty() &&
        $currentPage > 1 &&
        $nextPageUrl === null
      ) {
        Log::channel('sync')->info(
          "No more time entries found on page {$currentPage} using fallback, ending sync.",
          [
            'endpoint' => $endpoint,
          ]
        );
        break;
      }

      // Process time entries on the current page
      $syncedIdsOnPage = $this->processTimeEntryPage($entriesOnPage);
      $allSyncedProofhubEntryIds = $allSyncedProofhubEntryIds->merge(
        $syncedIdsOnPage
      );

      // --- Pagination Logic for Next Loop Iteration ---
      if ($nextPageUrl) {
        // Using Link header - reset fallback vars
        $currentPage = null;
        $totalPages = null;
      } else {
        // No Link header, using fallback
        if ($currentPage === 1) {
          // Set total pages ONLY on the first fallback request
          // $totalPagesFromHeader will be null if callPage detected the alltime bug
          $totalPages = $totalPagesFromHeader;
          if ($totalPages === null) {
            Log::channel('sync')->warning(
              'Terminating /alltime sync after page 1 due to unreliable fallback pagination.'
            );
            $syncTruncated = true; // Mark sync as truncated
          }
        }

        // Decide whether to continue
        if (
          $totalPages === null ||
          $currentPage === null ||
          $currentPage >= $totalPages
        ) {
          $currentPage = null; // Signal stop
        } else {
          $currentPage++; // Go to next page
        }
      }
    } while ($nextPageUrl !== null || $currentPage !== null);

    // Step 4: Remove obsolete local time entries for the synced period
    $this->removeObsoleteTimeEntries(
      $allSyncedProofhubEntryIds->unique(),
      $syncTruncated
    );

    Log::channel('sync')->info('Finished ProofHub time entry sync.', [
      'start_date' => $this->startDate,
      'end_date' => $this->endDate,
      'total_entries_processed' => $allSyncedProofhubEntryIds->count(),
      'unique_entries_found' => $allSyncedProofhubEntryIds->unique()->count(),
    ]);
  }

  /**
   * Builds initial parameters for the ProofHub API request.
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
   * Processes a single page of time entry data.
   *
   * @param Collection $entriesPage Time entries from one API page
   * @return Collection Collection of ProofHub IDs for successfully processed entries on this page
   */
  private function processTimeEntryPage(Collection $entriesPage): Collection
  {
    $syncedIdsOnPage = collect();

    $entriesPage->each(function ($entry) use ($syncedIdsOnPage) {
      // Reuse the existing single-entry processing logic
      $processedEntryId = $this->processSingleTimeEntry($entry);
      if ($processedEntryId !== null) {
        $syncedIdsOnPage->push($processedEntryId);
      }
    });

    Log::channel('sync')->debug('Processed time entry page.', [
      'entries_processed' => $entriesPage->count(),
      'synced_ids_count' => $syncedIdsOnPage->count(),
    ]);

    return $syncedIdsOnPage;
  }

  /**
   * Processes a single time entry from the API data.
   * Validates data, finds related models, and creates/updates the TimeEntry record.
   *
   * @param array $entry Time entry data from ProofHub
   * @return int|null The ProofHub ID of the synced entry, or null if skipped
   */
  private function processSingleTimeEntry(array $entry): ?int
  {
    $proofhubEntryId = data_get($entry, 'id');
    if (!$proofhubEntryId) {
      Log::channel('sync')->warning(
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
   * @param array $entry Time entry data
   * @return Carbon|null Parsed date or null if invalid
   */
  private function parseDateUtc(array $entry): ?Carbon
  {
    $dateString = data_get($entry, 'date');
    if (!$dateString) {
      Log::channel('sync')->warning(
        class_basename($this) . ': Skipping time entry - Date missing',
        ['time_entry_id' => data_get($entry, 'id')]
      );
      return null;
    }

    try {
      return Carbon::parse($dateString)->utc();
    } catch (Exception $e) {
      Log::channel('sync')->error(
        class_basename($this) . ': Skipping time entry - Invalid date format',
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
   * Parses the created_at date.
   *
   * @param array $entry Time entry data
   * @return Carbon Parsed created_at date or current time if invalid/missing
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
      Log::channel('sync')->warning(
        class_basename($this) .
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
   * Finds the trackable local user corresponding to the time entry creator.
   *
   * @param array $entry Time entry data
   * @return User|null The User model or null if not found or not trackable
   */
  private function findUserForTimeEntry(array $entry): ?User
  {
    $creatorProofhubId = data_get($entry, 'creator.id');
    if (!$creatorProofhubId) {
      Log::channel('sync')->warning(
        class_basename($this) . ': Skipping time entry - creator ID missing',
        ['time_entry_id' => data_get($entry, 'id')]
      );
      return null;
    }

    // Attempt to find the user and ensure they are trackable
    $user = User::where('proofhub_id', $creatorProofhubId)
      ->trackable() // Apply scope to only get trackable users
      ->first();

    if (!$user) {
      // Log why the user was skipped (either not found or not trackable)
      $this->logUserIssue($entry, $creatorProofhubId);
      return null;
    }

    return $user;
  }

  /**
   * Logs the reason why a user associated with a time entry was skipped.
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
      // If the user doesn't exist at all in the database
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
   * @return bool True if project exists locally, false otherwise
   */
  private function validateProject($projectId, array $entry): bool
  {
    if (!$projectId) {
      // Should have been caught earlier, but double-check
      Log::channel('sync')->warning(
        class_basename($this) . ': Skipping time entry - Project ID missing',
        ['time_entry_id' => data_get($entry, 'id')]
      );
      return false;
    }

    $projectExists = Project::where(
      'proofhub_project_id',
      $projectId
    )->exists();

    if (!$projectExists) {
      Log::channel('sync')->info(
        class_basename($this) .
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
   * Processes task information for a time entry.
   * Ensures the task exists locally if an ID is provided.
   *
   * @param array $entry Time entry data
   * @param string|int $projectId ProofHub project ID
   * @return array Contains 'taskId' (nullable int/string) and 'taskName' (nullable string)
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
   * Creates or updates a single TimeEntry record in the local database.
   *
   * @param array $entry Time entry data from ProofHub
   * @param User $user Local user model
   * @param string|int $projectId ProofHub project ID
   * @param Carbon $dateUtc Entry date (UTC)
   * @param Carbon $createdAtUtc Entry creation timestamp (UTC)
   * @param array $taskInfo Array containing 'taskId' and 'taskName'
   * @return void
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
   * Removes local time entries within the sync date range that no longer exist in ProofHub.
   *
   * @param Collection $syncedEntryIds All unique ProofHub IDs of entries found during this sync run
   * @param bool $syncTruncated Flag indicating if sync stopped early due to API issue
   * @return void
   */
  private function removeObsoleteTimeEntries(
    Collection $syncedEntryIds,
    bool $syncTruncated
  ): void {
    // *** SAFETY CHECK: Skip deletion if sync was truncated due to unreliable pagination ***
    if ($syncTruncated) {
      Log::channel('sync')->warning(
        'Skipping obsolete time entry deletion because sync was truncated due to unreliable API pagination.'
      );
      return;
    }

    // Define the date range Carbon objects for database query
    $startDate = Carbon::parse($this->startDate)->startOfDay();
    $endDate = Carbon::parse($this->endDate)->endOfDay();

    Log::channel('sync')->debug('Checking for obsolete time entries.', [
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
      Log::channel('sync')->info(
        'No obsolete time entries found within the date range.'
      );
      return; // No obsolete entries found
    }

    Log::channel('sync')->info(
      class_basename($this) .
        ": Deleting {$idsToDelete->count()} obsolete time entries within date range.",
      [
        'ids_to_delete' => $idsToDelete->all(),
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
          // Decide if we should continue or rethrow - for now, continue
        }
      });
  }
}
