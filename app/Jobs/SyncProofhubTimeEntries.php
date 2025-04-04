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
    $this->startDate = $startDate;
    $this->endDate = $endDate;
  }

  /**
   * Executes the synchronization process.
   *
   * This method performs the following operations:
   * 1. Builds parameters for the ProofHub API request
   * 2. Fetches time entries from ProofHub
   * 3. Processes and syncs each valid time entry
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

    // Step 3: Process each valid time entry
    $this->processTimeEntries($rawEntries);
  }

  /**
   * Builds parameters for the ProofHub API request.
   *
   * @return array Parameters for the API request
   */
  private function buildRequestParameters(): array
  {
    $params = [];

    // Set start date (default to 30 days ago if not provided)
    $params['from_date'] = $this->startDate
      ? $this->startDate
      : now()->subDays(30)->format('Y-m-d');

    // Set end date (default to today if not provided)
    $params['to_date'] = $this->endDate
      ? $this->endDate
      : now()->format('Y-m-d');

    return $params;
  }

  /**
   * Processes time entries from ProofHub.
   *
   * @param Collection $rawEntries Time entries from ProofHub API
   * @return void
   */
  private function processTimeEntries(Collection $rawEntries): void
  {
    if ($rawEntries->isEmpty()) {
      return;
    }

    $rawEntries->each(function ($entry) {
      $this->processTimeEntry($entry);
    });
  }

  /**
   * Processes a single time entry.
   *
   * @param array $entry Time entry data from ProofHub
   * @return void
   */
  private function processTimeEntry(array $entry): void
  {
    // Parse date and validate
    $dateUtc = $this->parseDateUtc($entry);
    if (!$dateUtc) {
      return;
    }

    // Parse created_at date
    $createdAtUtc = $this->parseCreatedAtUtc($entry);

    // Find user for this time entry
    $user = $this->findUserForTimeEntry($entry);
    if (!$user) {
      return;
    }

    // Validate project exists
    $projectId = data_get($entry, 'project.id');
    if (!$projectId || !$this->validateProject($projectId, $entry)) {
      return;
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
      return null;
    }

    return Carbon::parse($dateUtc)->utc();
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
    return $createdAt ? Carbon::parse($createdAt)->utc() : now()->utc();
  }

  /**
   * Finds the user for a time entry.
   *
   * @param array $entry Time entry data
   * @return User|null The user or null if not found
   */
  private function findUserForTimeEntry(array $entry): ?User
  {
    $creatorProofhubId = data_get($entry, 'creator.id');
    if (!$creatorProofhubId) {
      Log::channel('sync')->info('Skipping time entry - creator ID missing', [
        'time_entry_id' => data_get($entry, 'id'),
      ]);
      return null;
    }

    $user = User::where('proofhub_id', $creatorProofhubId)
      ->trackable()
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
    $userExists = User::where('proofhub_id', $creatorProofhubId)->exists();
    if ($userExists) {
      Log::channel('sync')->info(
        'Skipping time entry - user has do_not_track enabled',
        [
          'time_entry_id' => data_get($entry, 'id'),
          'creator_id' => $creatorProofhubId,
        ]
      );
    } else {
      Log::channel('sync')->info(
        'SyncProofHubTimeEntries: Skipping time entry - user not found in database',
        [
          'time_entry_id' => data_get($entry, 'id'),
          'creator_id' => $creatorProofhubId,
        ]
      );
    }
  }

  /**
   * Validates that a project exists for the time entry.
   *
   * @param string|int $projectId ProofHub project ID
   * @param array $entry Time entry data
   * @return bool Whether the project exists
   */
  private function validateProject($projectId, array $entry): bool
  {
    $projectExists = Project::where(
      'proofhub_project_id',
      $projectId
    )->exists();

    if (!$projectExists) {
      Log::channel('sync')->info('Skipping time entry - project not found', [
        'time_entry_id' => data_get($entry, 'id'),
        'project_id' => $projectId,
      ]);
      return false;
    }

    return true;
  }

  /**
   * Processes task information for a time entry.
   *
   * @param array $entry Time entry data
   * @param string|int $projectId ProofHub project ID
   * @return array Task information [taskId, taskName]
   */
  private function processTaskInfo(array $entry, $projectId): array
  {
    $taskId = null;
    $taskName = null;
    $taskData = data_get($entry, 'task');

    if ($taskData) {
      // The ProofHub API returns task information in a different format than expected
      // Time entries contain 'task.task_id' not 'task.id'
      $taskId = data_get($taskData, 'task_id');
      $taskName = data_get($taskData, 'task_name');

      // If there's also a subtask, prioritize it
      $subtaskId = data_get($taskData, 'subtask_id');
      $subtaskName = data_get($taskData, 'subtask_name');

      if ($subtaskId) {
        $taskId = $subtaskId;
        $taskName = $subtaskName;
      }

      // Create task if it doesn't exist
      if ($taskId) {
        Task::firstOrCreate(
          ['proofhub_task_id' => $taskId],
          [
            'proofhub_project_id' => $projectId,
            'name' => $taskName ?: 'Unknown Task',
          ]
        );
      }
    }

    return [
      'taskId' => $taskId,
      'taskName' => $taskName,
    ];
  }

  /**
   * Creates or updates a time entry in the database.
   *
   * @param array $entry Time entry data
   * @param User $user User who created the time entry
   * @param string|int $projectId ProofHub project ID
   * @param Carbon $dateUtc Date of the time entry (UTC)
   * @param Carbon $createdAtUtc When the time entry was created (UTC)
   * @param array $taskInfo Task information [taskId, taskName]
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
    // Build entry data
    $timeEntryData = [
      'user_id' => $user->id,
      'proofhub_user_id' => data_get($entry, 'creator.id'),
      'proofhub_time_entry_id' => data_get($entry, 'id'),
      'proofhub_project_id' => $projectId,
      'proofhub_task_id' => $taskInfo['taskId'],
      'description' => data_get($entry, 'description', ''),
      'date' => $dateUtc->toDateString(),
      'seconds' => ((int) data_get($entry, 'logged_mins', 0)) * 60,
      'proofhub_created_at' => $createdAtUtc,
    ];

    // Extract the unique constraint fields for lookup
    $uniqueConstraintData = [
      'user_id' => $timeEntryData['user_id'],
      'proofhub_project_id' => $timeEntryData['proofhub_project_id'],
      'date' => $timeEntryData['date'],
      'proofhub_task_id' => $timeEntryData['proofhub_task_id'],
    ];

    // Create or update the time entry
    TimeEntry::updateOrCreate($uniqueConstraintData, $timeEntryData);
  }
}
