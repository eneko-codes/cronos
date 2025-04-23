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
     */
    public int $priority = 2;

    /**
     * SyncProofhubTasks constructor.
     *
     * @param  ProofhubApiCalls  $proofhub  An instance of the ProofhubApiCalls service.
     */
    public function __construct(ProofhubApiCalls $proofhub)
    {
        $this->proofhub = $proofhub;
    }

    /**
     * Executes the synchronization process page by page.
     *
     * This method performs the following operations:
     * 1. Loops through pages of tasks fetched from ProofHub API using callPage.
     * 2. Processes tasks and their subtasks from each page.
     * 3. Collects all valid ProofHub task and subtask IDs encountered.
     * 4. Removes local tasks whose ProofHub IDs were not found in the sync.
     *
     *
     * @throws Exception If any part of the synchronization process fails
     */
    protected function execute(): void
    {
        $endpoint = 'alltodo';
        $allSyncedProofhubTaskIds = collect(); // Track all synced task/subtask IDs
        $currentPage = 1;
        $totalPages = 1; // Initialize for fallback
        $nextPageUrl = null;

        $baseUrl = config('services.proofhub.company_url');
        if (! $baseUrl) {
            throw new Exception('ProofHub company URL not configured.');
        }
        $initialUrl = "https://{$baseUrl}.proofhub.com/api/v3/{$endpoint}";

        Log::info('Starting ProofHub task sync.');

        do {
            // Determine URL and params for the API call
            $urlToCall = $nextPageUrl ?: $initialUrl;
            $paramsToCall = [];
            if ($nextPageUrl === null) {
                // Only add page param if using fallback URL
                $paramsToCall['page'] = $currentPage;
                $urlToCall = "https://{$baseUrl}.proofhub.com/api/v3/{$endpoint}"; // Ensure base URL for fallback
            }

            // Call API for the current page
            $pageResult = $this->proofhub->callPage(
                $urlToCall,
                $paramsToCall,
                $endpoint
            );
            $tasksOnPage = $pageResult['data'];
            $nextPageUrl = $pageResult['nextPageUrl'];
            $totalPagesFromHeader = $pageResult['totalPages'];

            // Check for empty page (after first page, using fallback)
            if (
                $tasksOnPage->isEmpty() &&
                $currentPage > 1 &&
                $nextPageUrl === null
            ) {
                Log::info(
                    "No more tasks found on page {$currentPage} using fallback, ending sync.",
                    [
                        'endpoint' => $endpoint,
                    ]
                );
                break;
            }

            // Process tasks on the current page
            $syncedIdsOnPage = $this->processTaskPage($tasksOnPage);
            $allSyncedProofhubTaskIds = $allSyncedProofhubTaskIds->merge(
                $syncedIdsOnPage
            );

            // --- Pagination Logic for Next Loop Iteration ---
            if ($nextPageUrl) {
                $currentPage = null;
                $totalPages = null;
            } elseif ($currentPage !== null) {
                if ($currentPage === 1 && $totalPagesFromHeader !== null) {
                    $totalPages = $totalPagesFromHeader;
                }
                if ($currentPage < $totalPages) {
                    $currentPage++;
                } else {
                    $currentPage = null;
                    Log::debug(
                        "Reached last page ({$totalPages}) via fallback for {$endpoint}."
                    );
                }
            }
        } while ($nextPageUrl !== null || $currentPage !== null);

        // Step 3: Remove local tasks that no longer exist in ProofHub
        $this->removeObsoleteTasks($allSyncedProofhubTaskIds->unique());

        Log::info('Finished ProofHub task sync.', [
            'total_tasks_subtasks_processed' => $allSyncedProofhubTaskIds->count(),
            'unique_tasks_subtasks_found' => $allSyncedProofhubTaskIds
                ->unique()
                ->count(),
        ]);
    }

    /**
     * Processes a single page of task data.
     *
     * @param  Collection  $tasksPage  Tasks from one API page
     * @return Collection Collection of synchronized task and subtask IDs from this page
     */
    private function processTaskPage(Collection $tasksPage): Collection
    {
        $syncedTaskIdsOnPage = collect();

        $tasksPage
            ->filter(fn ($taskData) => data_get($taskData, 'id'))
            ->each(function ($taskData) use ($syncedTaskIdsOnPage) {
                $taskId = data_get($taskData, 'id');
                $syncedTaskIdsOnPage->push($taskId); // Add main task ID

                $projectId = data_get($taskData, 'project.id');

                // Skip task if project doesn't exist locally
                if (! $this->validateProject($projectId, $taskId)) {
                    return; // Continue to the next task in the each loop
                }

                // Process the main task
                $task = $this->syncTaskRecord($taskData, $projectId);

                // Process task user assignments
                $this->syncTaskUsers($task, data_get($taskData, 'assigned', []));

                // Process subtasks if any - assumes subtasks are nested within the main task data
                $subtaskIds = $this->processSubtasks($taskData, $projectId);
                $syncedTaskIdsOnPage = $syncedTaskIdsOnPage->merge($subtaskIds); // Add subtask IDs
            });

        return $syncedTaskIdsOnPage;
    }

    /**
     * Validates that a project exists locally for the task.
     *
     * @param  mixed  $projectId  ProofHub project ID (can be null)
     * @param  mixed  $taskId  ProofHub task ID (for logging)
     * @return bool Whether the project exists locally
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
     * Creates or updates a single task/subtask record in the local database.
     *
     * @param  array  $taskData  Task or subtask data from ProofHub
     * @param  mixed  $projectId  ProofHub project ID
     * @return Task The updated or created Task model
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
     * @param  Task  $task  The task model
     * @param  array  $assignedUserIds  ProofHub user IDs assigned to the task
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
     * @param  array  $taskData  Main task data potentially containing a 'subtasks' array
     * @param  mixed  $projectId  ProofHub project ID of the main task
     * @return Collection Collection of ProofHub IDs for the processed subtasks
     */
    private function processSubtasks(array $taskData, $projectId): Collection
    {
        $syncedSubtaskIds = collect();
        $subtasks = collect(data_get($taskData, 'subtasks', [])); // Get subtasks or empty collection

        $subtasks
            ->filter(fn ($subtask) => data_get($subtask, 'id')) // Ensure subtask has an ID
            ->each(function ($subtask) use ($syncedSubtaskIds, $projectId) {
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
     * @param  Collection  $syncedTaskIds  All unique ProofHub task/subtask IDs found during sync
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
            ->get()
            ->each(fn (Task $t) => $t->delete());
    }
}
