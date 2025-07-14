<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Proofhub;

use App\Actions\Proofhub\CheckProofhubHealthAction;
use App\Clients\ProofhubApiClient;
use App\DataTransferObjects\Proofhub\ProofhubTaskDTO;
use App\Jobs\Sync\BaseSyncJob;
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

    protected ProofhubApiClient $proofhub;

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
    public function handle(): void
    {
        $allTasks = $this->proofhub->getTasks();
        $allSyncedProofhubTaskIds = collect();
        foreach ($allTasks as $task) {
            /** @var ProofhubTaskDTO $task */
            $mainTaskId = $task->id;
            if (! $mainTaskId) {
                continue;
            }
            $allSyncedProofhubTaskIds->push($mainTaskId);

            $projectId = $task->project['id'] ?? null;
            if (! $this->validateProject($projectId, $mainTaskId)) {
                continue;
            }
            $taskModel = $this->syncTaskRecord($task, $projectId);
            if ($taskModel === null) {
                continue;
            }
            $this->syncTaskUsers($taskModel, $task->assigned ?? []);
            $subtaskIds = $this->processSubtasks($task, $projectId);
            $allSyncedProofhubTaskIds = $allSyncedProofhubTaskIds->merge($subtaskIds);
        }
        $this->removeObsoleteTasks($allSyncedProofhubTaskIds->unique());
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
            Log::warning(class_basename(static::class).' Skipping task - Project ID missing in API data', [
                'job' => class_basename(static::class),
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
                class_basename(static::class).': Skipping task - Project not found locally',
                [
                    'job' => class_basename(static::class),
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
     * @param  ProofhubTaskDTO  $taskData  Task or subtask DTO from ProofHub.
     * @param  mixed  $projectId  ProofHub project ID.
     * @return Task The updated or created Task model.
     */
    private function syncTaskRecord(ProofhubTaskDTO $taskData, $projectId): ?Task
    {
        $taskId = $taskData->id;
        $taskName = $taskData->title;
        if ($taskName === null) {
            Log::warning(class_basename(static::class).': Skipping task with missing required name', ['job' => class_basename(static::class), 'taskData' => $taskData]);

            return null;
        }

        // Use updateOrCreate to sync the task based on its ProofHub ID
        return Task::updateOrCreate(
            ['proofhub_task_id' => $taskId],
            [
                'proofhub_project_id' => $projectId,
                'name' => $taskName,
                'status' => $taskData->status,
                'due_date' => $taskData->due_date,
                'description' => $taskData->description,
                'tags' => $taskData->tags,
                'priority' => $taskData->priority,
                'proofhub_created_at' => $taskData->proofhub_created_at,
                'proofhub_updated_at' => $taskData->proofhub_updated_at,
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
        // Log missing users
        $missingUserIds = array_diff($assignedUserIds, User::whereIn('proofhub_id', $assignedUserIds)->pluck('proofhub_id')->toArray());
        if (! empty($missingUserIds)) {
            foreach ($missingUserIds as $missingId) {
                Log::warning(class_basename(static::class).': ProofHub: Assigned user not found locally for task', [
                    'job' => class_basename(static::class),
                    'proofhub_user_id' => $missingId,
                    'proofhub_task_id' => $task->proofhub_task_id,
                ]);
            }
        }
        // Sync the relationship efficiently
        $task->users()->sync($localUserIds);
    }

    /**
     * Processes subtasks nested within a main task's data.
     *
     * @param  ProofhubTaskDTO  $taskData  Main task DTO potentially containing a 'subtasks' array.
     * @param  mixed  $projectId  ProofHub project ID of the main task.
     * @return Collection Collection of ProofHub IDs for the processed subtasks.
     */
    private function processSubtasks(ProofhubTaskDTO $taskData, $projectId): Collection
    {
        $syncedSubtaskIds = collect();
        $subtasks = collect($taskData->subtasks ?? []); // Get subtasks or empty collection

        $subtasks
            ->filter(fn ($subtask) => is_array($subtask) ? isset($subtask['id']) : isset($subtask->id)) // Ensure subtask has an ID
            ->each(function ($subtask) use ($syncedSubtaskIds, $projectId): void {
                $subtaskId = is_array($subtask) ? $subtask['id'] : $subtask->id;
                $syncedSubtaskIds->push($subtaskId);

                // Ensure subtask is an array for TaskDTO construction
                $subtaskArray = is_array($subtask) ? $subtask : (array) $subtask;
                $subtaskModel = $this->syncTaskRecord(new ProofhubTaskDTO(
                    $subtaskArray['id'] ?? null,
                    $subtaskArray['name'] ?? '',
                    $subtaskArray['project_id'] ?? null,
                    $subtaskArray['project'] ?? null,
                    $subtaskArray['assigned'] ?? [],
                    $subtaskArray['title'] ?? null,
                    $subtaskArray['subtasks'] ?? []
                ), $projectId);

                // Process subtask user assignments
                $assigned = $subtaskArray['assigned'] ?? [];
                $this->syncTaskUsers($subtaskModel, $assigned);
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
                class_basename(static::class).': No ProofHub tasks/subtasks found during sync, skipping obsolete task cleanup.'
            );

            return;
        }

        // Find local task IDs that were not in the synced list
        $obsoleteTaskIds = Task::whereNotIn(
            'proofhub_task_id',
            $syncedTaskIds
        )->pluck('proofhub_task_id');

        if ($obsoleteTaskIds->isEmpty()) {
            Log::info(
                class_basename(static::class).': No obsolete ProofHub tasks to delete.'
            );

            return;
        }

        Log::info(
            class_basename(static::class).": Deleting {$obsoleteTaskIds->count()} obsolete ProofHub tasks/subtasks.",
            [
                'job' => class_basename(static::class),
                'ids_to_delete' => $obsoleteTaskIds->all(),
            ]
        );

        // Delete obsolete tasks individually to trigger model events
        Task::whereIn('proofhub_task_id', $obsoleteTaskIds)
            ->with(['users', 'timeEntries'])
            ->get()
            ->each(fn (Task $t) => $t->delete());
    }

    public function failed(): void
    {
        app(CheckProofhubHealthAction::class)($this->proofhub);
    }
}
