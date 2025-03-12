<?php

namespace App\Jobs;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\ProofhubApiCalls;
use Exception;
use Illuminate\Support\Collection;

/**
 * Class SyncProofhubTasks
 *
 * Synchronizes tasks from ProofHub into the local database,
 * and invalidates the entire cache store upon completion.
 */
class SyncProofhubTasks extends BaseSyncJob
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
  public function __construct(ProofhubApiCalls $proofhub)
  {
    $this->proofhub = $proofhub;
  }

  protected function execute(): void
  {
    $tasks = $this->proofhub->getTasks();

    foreach ($tasks as $taskData) {
      $taskId = data_get($taskData, 'id');
      $projectId = data_get($taskData, 'project.id');
      $taskName = data_get($taskData, 'title');
      $assignedUserIds = data_get($taskData, 'assigned', []);

      // Ensure local project exists
      $project = Project::find($projectId);

      // Upsert the task
      $task = Task::updateOrCreate(
        ['proofhub_task_id' => $taskId],
        [
          'proofhub_project_id' => $projectId,
          'name' => $taskName,
        ]
      );

      // Find local users who match the assigned IDs
      $localUserIds = User::whereIn('proofhub_id', $assignedUserIds)
        ->where('do_not_track', false)
        ->pluck('id');

      // Manually detach any users not in $localUserIds
      $existingUserIds = $task->users()->pluck('user_id');
      $toDetach = $existingUserIds->diff($localUserIds);
      foreach ($toDetach as $userId) {
        $task->users()->detach($userId);
      }

      // Manually attach any new users
      $toAttach = $localUserIds->diff($existingUserIds);
      foreach ($toAttach as $userId) {
        $task->users()->attach($userId);
      }
    }

    // Delete local tasks not present in ProofHub
    $proofhubTaskIds = collect($tasks)->pluck('id');
    $localTaskIds = Task::pluck('proofhub_task_id');
    $tasksToDelete = $localTaskIds->diff($proofhubTaskIds);

    if ($tasksToDelete->isNotEmpty()) {
      Task::whereIn('proofhub_task_id', $tasksToDelete)
        ->get()
        ->each(fn(Task $t) => $t->delete());
    }
  }
}
