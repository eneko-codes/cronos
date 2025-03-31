<?php

namespace App\Jobs;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\ProofhubApiCalls;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

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
    $syncedTaskIds = [];

    foreach ($tasks as $taskData) {
      // Get the main task ID from the response
      $taskId = data_get($taskData, 'id');
      if (!$taskId) {
        continue;
      }

      $syncedTaskIds[] = $taskId;

      $projectId = data_get($taskData, 'project.id');
      $taskName = data_get($taskData, 'title');
      $assignedUserIds = data_get($taskData, 'assigned', []);

      // Ensure local project exists
      $project = Project::where('proofhub_project_id', $projectId)->first();
      if (!$project) {
        Log::channel('sync')->info('Skipping task - project not found', [
          'task_id' => $taskId,
          'project_id' => $projectId,
        ]);
        continue;
      }

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

      // Check if there are subtasks and process them
      $subtasks = data_get($taskData, 'subtasks');
      if ($subtasks && is_array($subtasks)) {
        foreach ($subtasks as $subtask) {
          $subtaskId = data_get($subtask, 'id');
          if (!$subtaskId) {
            continue;
          }

          $syncedTaskIds[] = $subtaskId;

          $subtaskName = data_get($subtask, 'title');
          $subtaskAssignedUserIds = data_get($subtask, 'assigned', []);

          // Upsert the subtask
          $subtaskModel = Task::updateOrCreate(
            ['proofhub_task_id' => $subtaskId],
            [
              'proofhub_project_id' => $projectId,
              'name' => $subtaskName,
            ]
          );

          // Process subtask user assignments
          $subtaskLocalUserIds = User::whereIn(
            'proofhub_id',
            $subtaskAssignedUserIds
          )
            ->where('do_not_track', false)
            ->pluck('id');

          // Detach users not in assigned list
          $existingSubtaskUserIds = $subtaskModel->users()->pluck('user_id');
          $subtaskToDetach = $existingSubtaskUserIds->diff(
            $subtaskLocalUserIds
          );
          foreach ($subtaskToDetach as $userId) {
            $subtaskModel->users()->detach($userId);
          }

          // Attach newly assigned users
          $subtaskToAttach = $subtaskLocalUserIds->diff(
            $existingSubtaskUserIds
          );
          foreach ($subtaskToAttach as $userId) {
            $subtaskModel->users()->attach($userId);
          }
        }
      }
    }

    // Delete local tasks not present in ProofHub
    if (!empty($syncedTaskIds)) {
      $localTaskIds = Task::pluck('proofhub_task_id');
      $tasksToDelete = $localTaskIds->diff($syncedTaskIds);

      if ($tasksToDelete->isNotEmpty()) {
        Task::whereIn('proofhub_task_id', $tasksToDelete)
          ->get()
          ->each(fn(Task $t) => $t->delete());
      }
    }
  }
}
