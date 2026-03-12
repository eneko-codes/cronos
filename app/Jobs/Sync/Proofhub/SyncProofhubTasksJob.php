<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Proofhub;

use App\Actions\Proofhub\CheckProofhubHealthAction;
use App\Actions\Proofhub\ProcessProofhubTaskAction;
use App\Clients\ProofhubApiClient;
use App\DataTransferObjects\Proofhub\ProofhubTaskDTO;
use App\Jobs\Sync\BaseSyncJob;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Services\NotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Job to synchronize ProofHub tasks with the local tasks table.
 *
 * This job fetches all tasks from ProofHub and processes each one to ensure
 * the local database reflects the current state of ProofHub.
 *
 * Responsibilities:
 * - Fetch all tasks from ProofHub
 * - Create or update local tasks
 * - Delete tasks no longer present in ProofHub (with dependencies)
 */
class SyncProofhubTasksJob extends BaseSyncJob
{
    /**
     * The priority of the job in the queue.
     */
    public int $priority = 3;

    protected ProofhubApiClient $proofhub;

    /**
     * Constructs a new SyncProofhubTasksJob job.
     *
     * @param  ProofhubApiClient  $proofhub  The ProofHub API client.
     */
    public function __construct(ProofhubApiClient $proofhub)
    {
        $this->proofhub = $proofhub;
    }

    /**
     * Main entry point for the job's sync logic.
     *
     * Fetches tasks from ProofHub and processes each one, then deletes
     * tasks that are no longer present in ProofHub.
     */
    public function handle(): void
    {
        $tasks = $this->proofhub->getTasks();

        // Extract ProofHub IDs before processing
        $apiIds = $tasks->pluck('id')->filter();

        // Process each task DTO
        $tasks->each(function (ProofhubTaskDTO $task): void {
            (new ProcessProofhubTaskAction)->execute($task);
        });

        // Cleanup: delete tasks no longer in ProofHub
        $this->cleanupMissingTasks($apiIds);
    }

    /**
     * Delete tasks that are no longer present in the ProofHub API response.
     *
     * Deletes dependencies first (task_user pivot) and nullifies time entry
     * task references to avoid foreign key constraint violations.
     *
     * @param  Collection  $apiIds  Collection of ProofHub task IDs from the API response.
     */
    private function cleanupMissingTasks(Collection $apiIds): void
    {
        // Find orphan task IDs (tasks not in the API response)
        $orphanIds = Task::whereNotIn('proofhub_task_id', $apiIds)
            ->pluck('proofhub_task_id');

        if ($orphanIds->isEmpty()) {
            return;
        }

        // Delete dependencies first to avoid FK constraint violations
        // 1. Delete task_user pivot records
        $deletedTaskUsers = DB::table('task_user')
            ->whereIn('proofhub_task_id', $orphanIds)
            ->delete();

        // 2. Nullify task references in time entries (don't delete the time entries)
        $updatedTimeEntries = TimeEntry::whereIn('proofhub_task_id', $orphanIds)
            ->update(['proofhub_task_id' => null]);

        // 3. Delete the tasks
        $deletedTasks = Task::whereIn('proofhub_task_id', $orphanIds)->delete();

        if ($deletedTasks > 0) {
            Log::debug('SyncProofhubTasksJob: Deleted tasks no longer in ProofHub', [
                'deleted_tasks' => $deletedTasks,
                'deleted_task_users' => $deletedTaskUsers,
                'nullified_time_entries' => $updatedTimeEntries,
            ]);
        }
    }

    /**
     * Handle a job failure.
     *
     * This method is called automatically by Laravel if the job fails after all retry attempts.
     * It triggers a health check for the ProofHub API and notifies admins if the API is down.
     */
    public function failed(): void
    {
        $notificationService = app(NotificationService::class);
        $checkHealth = new CheckProofhubHealthAction($notificationService);
        $checkHealth($this->proofhub);
    }
}
