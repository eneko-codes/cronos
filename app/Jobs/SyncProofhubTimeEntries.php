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
 * and invalidates the entire cache store upon completion.
 */
class SyncProofhubTimeEntries extends BaseSyncJob
{
  /**
   * The priority of the job in the queue.
   *
   * @var int
   */
  public int $priority = 2;

  /**
   * Removed protected ProofhubApiCalls $proofhub;
   */
  protected ?string $startDate;
  protected ?string $endDate;

  public function __construct(
    ProofhubApiCalls $proofhub,
    ?string $startDate = null,
    ?string $endDate = null
  ) {
    $this->proofhub = $proofhub;
    $this->startDate = $startDate;
    $this->endDate = $endDate;
  }

  protected function execute(): void
  {
    // Build parameters
    $params = [];
    if ($this->startDate) {
      $params['from_date'] = $this->startDate;
    } else {
      // Default to 30 days ago if no start date is provided
      $params['from_date'] = now()->subDays(30)->format('Y-m-d');
    }

    if ($this->endDate) {
      $params['to_date'] = $this->endDate;
    } else {
      // Default to today if no end date is provided
      $params['to_date'] = now()->format('Y-m-d');
    }

    // Fetch entries
    $rawEntries = collect($this->proofhub->getAllTime($params));

    // If no entries, stop
    if ($rawEntries->isEmpty()) {
      return;
    }

    // Process each entry
    foreach ($rawEntries as $entry) {
      $dateUtc = data_get($entry, 'date');
      if ($dateUtc) {
        $dateUtc = Carbon::parse($dateUtc)->utc();
      } else {
        continue;
      }

      // created_at as UTC or fallback now
      $createdAt = data_get($entry, 'created_at');
      $createdAtUtc = $createdAt
        ? Carbon::parse($createdAt)->utc()
        : now()->utc();

      // Map creator ID -> local user
      $creatorProofhubId = data_get($entry, 'creator.id');
      if (!$creatorProofhubId) {
        Log::channel('sync')->info('Skipping time entry - creator ID missing', [
          'time_entry_id' => data_get($entry, 'id'),
        ]);
        continue;
      }

      $user = User::where('proofhub_id', $creatorProofhubId)
        ->trackable()
        ->first();

      if (!$user) {
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
        continue;
      }

      // Retrieve project
      $projectId = data_get($entry, 'project.id');
      if (!$projectId) {
        continue;
      }

      // Check if project exists locally
      $projectExists = Project::where(
        'proofhub_project_id',
        $projectId
      )->exists();
      if (!$projectExists) {
        Log::channel('sync')->info('Skipping time entry - project not found', [
          'time_entry_id' => data_get($entry, 'id'),
          'project_id' => $projectId,
        ]);
        continue;
      }

      // Extract task information
      $taskData = data_get($entry, 'task');
      $taskId = null;
      $taskName = null;

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
      }

      // Check if the task exists in our database, or create it if needed
      if ($taskId) {
        $task = Task::firstOrCreate(
          ['proofhub_task_id' => $taskId],
          [
            'proofhub_project_id' => $projectId,
            'name' => $taskName ?: 'Unknown Task',
          ]
        );
      }

      // Build entry data
      $timeEntryData = [
        'user_id' => $user->id,
        'proofhub_user_id' => $creatorProofhubId,
        'proofhub_time_entry_id' => data_get($entry, 'id'),
        'proofhub_project_id' => $projectId,
        'proofhub_task_id' => $taskId,
        'description' => data_get($entry, 'description', ''),
        'date' => $dateUtc->toDateString(),
        'seconds' => ((int) data_get($entry, 'logged_mins', 0)) * 60,
        'proofhub_created_at' => $createdAtUtc,
      ];

      // Extract the unique constraint fields from the time entry data
      $uniqueConstraintData = [
        'user_id' => $timeEntryData['user_id'],
        'proofhub_project_id' => $timeEntryData['proofhub_project_id'],
        'date' => $timeEntryData['date'],
      ];

      // Handle task_id properly for the unique constraint
      if ($timeEntryData['proofhub_task_id'] === null) {
        $uniqueConstraintData['proofhub_task_id'] = null;
      } else {
        $uniqueConstraintData['proofhub_task_id'] =
          $timeEntryData['proofhub_task_id'];
      }

      // Use updateOrCreate to either update an existing entry or create a new one
      // This prevents unique constraint violations by checking for existing records
      $timeEntry = TimeEntry::updateOrCreate(
        $uniqueConstraintData,
        $timeEntryData
      );
    }
  }
}
