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
    }
    if ($this->endDate) {
      $params['to_date'] = $this->endDate;
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
        continue;
      }

      $user = User::where('proofhub_id', $creatorProofhubId)
        ->where('do_not_track', false)
        ->first();

      if (!$user) {
        continue;
      }

      // Retrieve project
      $projectId = data_get($entry, 'project.id');
      if (!$projectId || !Project::find($projectId)) {
        continue;
      }

      // Retrieve optional task
      $taskId = null;
      $taskProofhubId = data_get($entry, 'task.id');
      if ($taskProofhubId) {
        $task = Task::where('proofhub_task_id', $taskProofhubId)->first();
        if ($task) {
          $taskId = $task->proofhub_task_id;
        }
      }

      // Build entry data
      $timeEntryData = [
        'proofhub_time_entry_id' => data_get($entry, 'id'),
        'user_id' => $user->id,
        'proofhub_project_id' => $projectId,
        'proofhub_task_id' => $taskId,
        'status' => data_get($entry, 'status', 'none'),
        'description' => data_get($entry, 'description', ''),
        'date' => $dateUtc->toDateString(),
        'duration_seconds' =>
          ((int) data_get($entry, 'logged_hours', 0)) * 3600 +
          ((int) data_get($entry, 'logged_mins', 0)) * 60,
        'proofhub_created_at' => $createdAtUtc,
      ];

      // Upsert
      $timeEntry = TimeEntry::find($timeEntryData['proofhub_time_entry_id']);
      if ($timeEntry) {
        $timeEntry->update($timeEntryData);
      } else {
        TimeEntry::create($timeEntryData);
      }
    }
  }
}
