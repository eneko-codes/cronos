<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Proofhub;

use App\Actions\Proofhub\CheckProofhubHealthAction;
use App\Actions\Proofhub\ProcessProofhubProjectAction;
use App\Clients\ProofhubApiClient;
use App\DataTransferObjects\Proofhub\ProofhubProjectDTO;
use App\Jobs\Sync\BaseSyncJob;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Services\NotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Job to synchronize ProofHub projects with the local projects table.
 *
 * This job fetches all projects from ProofHub and processes each one to ensure
 * the local database reflects the current state of ProofHub.
 *
 * Responsibilities:
 * - Fetch all projects from ProofHub
 * - Create or update local projects
 * - Delete projects no longer present in ProofHub (with dependencies)
 */
class SyncProofhubProjectsJob extends BaseSyncJob
{
    /**
     * The priority of the job in the queue.
     */
    public int $priority = 2;

    protected ProofhubApiClient $proofhub;

    /**
     * Constructs a new SyncProofhubProjectsJob job.
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
     * Fetches projects from ProofHub and processes each one, then deletes
     * projects that are no longer present in ProofHub.
     */
    public function handle(): void
    {
        $projects = $this->proofhub->getProjects();

        // Extract ProofHub IDs before processing
        $apiIds = $projects->pluck('id')->filter();

        // Process each project DTO
        $projects->each(function (ProofhubProjectDTO $project): void {
            (new ProcessProofhubProjectAction)->execute($project);
        });

        // Cleanup: delete projects no longer in ProofHub
        $this->cleanupMissingProjects($apiIds);
    }

    /**
     * Delete projects that are no longer present in the ProofHub API response.
     *
     * Deletes dependencies first (time entries, tasks, pivot tables) to avoid
     * foreign key constraint violations.
     *
     * @param  Collection  $apiIds  Collection of ProofHub project IDs from the API response.
     */
    private function cleanupMissingProjects(Collection $apiIds): void
    {
        // Find orphan project IDs (projects not in the API response)
        $orphanIds = Project::whereNotIn('proofhub_project_id', $apiIds)
            ->pluck('proofhub_project_id');

        if ($orphanIds->isEmpty()) {
            return;
        }

        // Delete dependencies first to avoid FK constraint violations
        // 1. Delete time entries for orphan projects
        $deletedTimeEntries = TimeEntry::whereIn('proofhub_project_id', $orphanIds)->delete();

        // 2. Delete project_user pivot records
        $deletedProjectUsers = DB::table('project_user')
            ->whereIn('proofhub_project_id', $orphanIds)
            ->delete();

        // 3. Delete task_user pivot records for tasks in orphan projects
        $orphanTaskIds = Task::whereIn('proofhub_project_id', $orphanIds)
            ->pluck('proofhub_task_id');
        $deletedTaskUsers = DB::table('task_user')
            ->whereIn('proofhub_task_id', $orphanTaskIds)
            ->delete();

        // 4. Delete tasks for orphan projects
        $deletedTasks = Task::whereIn('proofhub_project_id', $orphanIds)->delete();

        // 5. Finally, delete the projects
        $deletedProjects = Project::whereIn('proofhub_project_id', $orphanIds)->delete();

        if ($deletedProjects > 0) {
            Log::debug('SyncProofhubProjectsJob: Deleted projects no longer in ProofHub', [
                'deleted_projects' => $deletedProjects,
                'deleted_tasks' => $deletedTasks,
                'deleted_time_entries' => $deletedTimeEntries,
                'deleted_project_users' => $deletedProjectUsers,
                'deleted_task_users' => $deletedTaskUsers,
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
