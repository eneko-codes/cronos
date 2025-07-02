<?php

declare(strict_types=1);

namespace App\Jobs\Sync;

use App\Clients\ProofhubApiClient;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Job to synchronize ProofHub task and subtask data with the local tasks table.
 *
 * Ensures the local tasks database reflects the current state of ProofHub, including:
 * - Creating or updating tasks, subtasks, and user assignments
 * - Removing obsolete tasks and subtasks
 * - Logging the sync process and results
 */
class SyncProofhubTasks extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 2;

    /**
     * Constructs a new SyncProofhubTasks job instance.
     *
     * @param  ProofhubApiClient  $proofhub  The ProofHub API client instance.
     */
    public function __construct(ProofhubApiClient $proofhub)
    {
        $this->proofhub = $proofhub;
    }

    /**
     * Main entry point for the job's sync logic.
     *
     * Performs the following operations:
     * 1. Loops through all tasks fetched from ProofHub API
     * 2. Processes each task and its subtasks, updating local records and user assignments
     * 3. Removes local tasks whose ProofHub IDs were not found in the sync
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    protected function execute(): void
    {
        Log::info('Starting ProofHub task sync.');
        $allTasks = $this->proofhub->getTasks();
        $allSyncedProofhubTaskIds = collect();
        foreach ($allTasks as $taskData) {
            $mainTaskId = data_get($taskData, 'id');
            if (! $mainTaskId) {
                continue;
            }
            $allSyncedProofhubTaskIds->push($mainTaskId);

            $projectId = data_get($taskData, 'project.id');
            if (! $this->validateProject($projectId, $mainTaskId)) {
                continue;
            }
            $task = $this->syncTaskRecord($taskData, $projectId);
            $this->syncTaskUsers($task, data_get($taskData, 'assigned', []));
            $subtaskIds = $this->processSubtasks($taskData, $projectId);
            $allSyncedProofhubTaskIds = $allSyncedProofhubTaskIds->merge($subtaskIds);
        }
        $this->removeObsoleteTasks($allSyncedProofhubTaskIds->unique());
        Log::info('Finished ProofHub task sync.', [
            'total_tasks_subtasks_processed' => $allSyncedProofhubTaskIds->count(),
            'unique_tasks_subtasks_found' => $allSyncedProofhubTaskIds->unique()->count(),
        ]);
    }

    /**
     * Validates that a project exists locally for the task.
     *
     * @param  mixed  $projectId  ProofHub project ID (can be null).
     * @param  mixed  $taskId  ProofHub task ID (for logging).
     * @return bool Whether the project exists locally.
     */
    private function validateProject($projectId, $taskId): bool
    {
        if (! $projectId) {
            Log::warning('Skipping task - Project ID missing in API data', [
                'task_id' => $taskId,
            ]);

            return false;
        }

        $projectExists = Project::where(
            'proofhub_project_id',
            $projectId
        )->exists();

        if (! $projectExists) {
            Log::info(
                class_basename($this).': Skipping task - Project not found locally',
                [
                    'task_id' => $taskId,
                    'proofhub_project_id' => $projectId,
                ]
            );

            return false;
        }

        return true;
    }

    /**
     * Creates or updates a single task or subtask record in the local database.
     *
     * @param  array  $taskData  Task or subtask data from ProofHub.
     * @param  mixed  $projectId  ProofHub project ID.
     * @return Task The updated or created Task model.
     */
    private function syncTaskRecord(array $taskData, $projectId): Task
    {
        $taskId = data_get($taskData, 'id');
        $taskName = data_get($taskData, 'title');

        // Use updateOrCreate to sync the task based on its ProofHub ID
        return Task::updateOrCreate(
            ['proofhub_task_id' => $taskId],
            [
                'proofhub_project_id' => $projectId,
                'name' => $taskName ?: 'Untitled Task', // Provide a default name if missing
            ]
        );
    }

    /**
     * Syncs user assignments for a task using the efficient sync() method.
     *
     * @param  Task  $task  The task model.
     * @param  array  $assignedUserIds  ProofHub user IDs assigned to the task.
     */
    private function syncTaskUsers(Task $task, array $assignedUserIds): void
    {
        // Find local user IDs for trackable users matching the assigned ProofHub IDs
        $localUserIds = User::whereIn('proofhub_id', $assignedUserIds)
            ->trackable()
            ->pluck('id');

        // Sync the relationship efficiently
        $task->users()->sync($localUserIds);
    }

    /**
     * Processes subtasks nested within a main task's data.
     *
     * @param  array  $taskData  Main task data potentially containing a 'subtasks' array.
     * @param  mixed  $projectId  ProofHub project ID of the main task.
     * @return Collection Collection of ProofHub IDs for the processed subtasks.
     */
    private function processSubtasks(array $taskData, $projectId): Collection
    {
        $syncedSubtaskIds = collect();
        $subtasks = collect(data_get($taskData, 'subtasks', [])); // Get subtasks or empty collection

        $subtasks
            ->filter(fn ($subtask) => data_get($subtask, 'id')) // Ensure subtask has an ID
            ->each(function ($subtask) use ($syncedSubtaskIds, $projectId): void {
                $subtaskId = data_get($subtask, 'id');
                $syncedSubtaskIds->push($subtaskId);

                // Create or update the subtask record
                $subtaskModel = $this->syncTaskRecord($subtask, $projectId);

                // Process subtask user assignments
                $this->syncTaskUsers($subtaskModel, data_get($subtask, 'assigned', []));
            });

        return $syncedSubtaskIds;
    }

    /**
     * Removes local tasks that no longer exist in ProofHub.
     *
     * @param  Collection  $syncedTaskIds  All unique ProofHub task/subtask IDs found during sync.
     */
    private function removeObsoleteTasks(Collection $syncedTaskIds): void
    {
        if ($syncedTaskIds->isEmpty()) {
            Log::info(
                'No ProofHub tasks/subtasks found during sync, skipping obsolete task cleanup.'
            );

            return;
        }

        // Find local task IDs that were not in the synced list
        $obsoleteTaskIds = Task::whereNotIn(
            'proofhub_task_id',
            $syncedTaskIds
        )->pluck('proofhub_task_id');

        if ($obsoleteTaskIds->isEmpty()) {
            Log::info('No obsolete ProofHub tasks to delete.');

            return;
        }

        Log::info(
            "Deleting {$obsoleteTaskIds->count()} obsolete ProofHub tasks/subtasks.",
            [
                'ids_to_delete' => $obsoleteTaskIds->all(),
            ]
        );

        // Delete obsolete tasks individually to trigger model events
        Task::whereIn('proofhub_task_id', $obsoleteTaskIds)
            ->with(['users', 'timeEntries'])
            ->get()
            ->each(fn (Task $t) => $t->delete());
    }
}
