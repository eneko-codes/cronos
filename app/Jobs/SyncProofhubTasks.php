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
 * including main tasks, subtasks, and their user assignments.
 */
class SyncProofhubTasks extends BaseSyncJob
{
  /**
   * The priority of the job in the queue.
   * Lower numbers indicate higher priority.
   *
   * @var int
   */
  public int $priority = 2;

  /**
   * SyncProofhubTasks constructor.
   *
   * @param ProofhubApiCalls $proofhub An instance of the ProofhubApiCalls service.
   */
  public function __construct(ProofhubApiCalls $proofhub)
  {
    $this->proofhub = $proofhub;
  }

  /**
   * Executes the synchronization process.
   *
   * This method performs the following operations:
   * 1. Fetches tasks from ProofHub API
   * 2. Processes tasks and their subtasks
   * 3. Removes local tasks that no longer exist in ProofHub
   *
   * @return void
   *
   * @throws Exception If any part of the synchronization process fails
   */
  protected function execute(): void
  {
    // Step 1: Fetch tasks from ProofHub API
    $tasks = $this->proofhub->getTasks();

    // Step 2: Process all tasks and collect valid task IDs
    $syncedTaskIds = $this->processTasks($tasks);

    // Step 3: Remove local tasks that no longer exist in ProofHub
    $this->removeObsoleteTasks($syncedTaskIds);
  }

  /**
   * Processes tasks from ProofHub and returns their IDs.
   *
   * @param Collection $tasks Tasks from ProofHub API
   * @return Collection Collection of synchronized task IDs
   */
  private function processTasks(Collection $tasks): Collection
  {
    $syncedTaskIds = collect();

    $tasks
      ->filter(fn($taskData) => data_get($taskData, 'id'))
      ->each(function ($taskData) use ($syncedTaskIds) {
        $taskId = data_get($taskData, 'id');
        $syncedTaskIds->push($taskId);

        $projectId = data_get($taskData, 'project.id');

        // Skip task if project doesn't exist
        if (!$this->validateProject($projectId, $taskId)) {
          return;
        }

        // Process the main task
        $task = $this->syncTask($taskData, $projectId);

        // Process task user assignments
        $this->syncTaskUsers($task, data_get($taskData, 'assigned', []));

        // Process subtasks if any
        $subtaskIds = $this->processSubtasks($taskData, $projectId);
        $syncedTaskIds = $syncedTaskIds->merge($subtaskIds);
      });

    return $syncedTaskIds;
  }

  /**
   * Validates that a project exists for the task.
   *
   * @param string|int $projectId ProofHub project ID
   * @param string|int $taskId ProofHub task ID
   * @return bool Whether the project exists
   */
  private function validateProject($projectId, $taskId): bool
  {
    $project = Project::where('proofhub_project_id', $projectId)->first();

    if (!$project) {
      Log::channel('sync')->info('Skipping task - project not found', [
        'task_id' => $taskId,
        'project_id' => $projectId,
      ]);
      return false;
    }

    return true;
  }

  /**
   * Creates or updates a task in the local database.
   *
   * @param array $taskData Task data from ProofHub
   * @param string|int $projectId ProofHub project ID
   * @return Task The updated or created task
   */
  private function syncTask(array $taskData, $projectId): Task
  {
    $taskId = data_get($taskData, 'id');
    $taskName = data_get($taskData, 'title');

    return Task::updateOrCreate(
      ['proofhub_task_id' => $taskId],
      [
        'proofhub_project_id' => $projectId,
        'name' => $taskName,
      ]
    );
  }

  /**
   * Syncs user assignments for a task.
   *
   * @param Task $task The task to sync users for
   * @param array $assignedUserIds ProofHub user IDs assigned to the task
   * @return void
   */
  private function syncTaskUsers(Task $task, array $assignedUserIds): void
  {
    // Find local users who match the assigned IDs
    $localUserIds = User::whereIn('proofhub_id', $assignedUserIds)
      ->trackable()
      ->pluck('id');

    // Get existing task user IDs
    $existingUserIds = $task->users()->pluck('user_id');

    // Detach users no longer assigned to the task
    $this->detachUsers($task, $existingUserIds->diff($localUserIds));

    // Attach newly assigned users
    $this->attachUsers($task, $localUserIds->diff($existingUserIds));
  }

  /**
   * Detaches users from a task.
   *
   * @param Task $task The task to detach users from
   * @param Collection $userIdsToDetach User IDs to detach
   * @return void
   */
  private function detachUsers(Task $task, Collection $userIdsToDetach): void
  {
    $userIdsToDetach->each(function ($userId) use ($task) {
      $task->users()->detach($userId);
    });
  }

  /**
   * Attaches users to a task.
   *
   * @param Task $task The task to attach users to
   * @param Collection $userIdsToAttach User IDs to attach
   * @return void
   */
  private function attachUsers(Task $task, Collection $userIdsToAttach): void
  {
    $userIdsToAttach->each(function ($userId) use ($task) {
      $task->users()->attach($userId);
    });
  }

  /**
   * Processes subtasks of a main task.
   *
   * @param array $taskData Main task data containing subtasks
   * @param string|int $projectId ProofHub project ID
   * @return Collection Collection of subtask IDs
   */
  private function processSubtasks(array $taskData, $projectId): Collection
  {
    $subtaskIds = collect();
    $subtasks = collect(data_get($taskData, 'subtasks', []));

    $subtasks
      ->filter(fn($subtask) => data_get($subtask, 'id'))
      ->each(function ($subtask) use ($subtaskIds, $projectId) {
        $subtaskId = data_get($subtask, 'id');
        $subtaskIds->push($subtaskId);

        // Create or update the subtask
        $subtaskModel = $this->syncTask($subtask, $projectId);

        // Process subtask user assignments
        $this->syncTaskUsers($subtaskModel, data_get($subtask, 'assigned', []));
      });

    return $subtaskIds;
  }

  /**
   * Removes local tasks that no longer exist in ProofHub.
   *
   * @param Collection $syncedTaskIds IDs of tasks that exist in ProofHub
   * @return void
   */
  private function removeObsoleteTasks(Collection $syncedTaskIds): void
  {
    if ($syncedTaskIds->isEmpty()) {
      return;
    }

    Task::pluck('proofhub_task_id')
      ->diff($syncedTaskIds)
      ->pipe(function ($tasksToDelete) {
        if ($tasksToDelete->isEmpty()) {
          return;
        }

        Task::whereIn('proofhub_task_id', $tasksToDelete)
          ->get()
          ->each(fn(Task $t) => $t->delete());
      });
  }
}
