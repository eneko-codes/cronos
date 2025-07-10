<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Proofhub;

use App\Clients\ProofhubApiClient;
use App\DataTransferObjects\Proofhub\ProofhubProjectDTO;
use App\Jobs\Sync\BaseSyncJob;
use App\Models\Project;
use App\Models\User;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Job to synchronize ProofHub project data with the local projects table.
 *
 * Ensures the local projects database reflects the current state of ProofHub, including:
 * - Creating or updating projects and user assignments
 * - Removing obsolete projects
 * - Logging the sync process and results
 */
class SyncProofhubProjects extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 2;

    /**
     * Constructs a new SyncProofhubProjects job instance.
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
     * 1. Loops through all projects fetched from ProofHub API
     * 2. Processes each project, updating local records and user assignments
     * 3. Removes local projects whose ProofHub IDs were not found in the sync
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    protected function execute(): void
    {
        $allProjects = $this->proofhub->getProjects();
        $allSyncedProofhubProjectIds = collect();
        foreach ($allProjects as $project) {
            /** @var ProofhubProjectDTO $project */
            $projectId = $project->id;
            if (! $projectId) {
                continue;
            }
            $projectName = $project->title;
            $assignedUserIds = $project->assigned ?? [];
            $projectModel = Project::updateOrCreate(
                ['proofhub_project_id' => $projectId],
                [
                    'name' => $projectName,
                    'status' => $project->status,
                    'description' => $project->description,
                    'proofhub_created_at' => $project->proofhub_created_at,
                    'proofhub_updated_at' => $project->proofhub_updated_at,
                    'proofhub_owner_id' => $project->owner_id,
                ]
            );
            $this->syncProjectUsers($projectModel, $assignedUserIds);
            $allSyncedProofhubProjectIds->push($projectId);
        }
        $this->removeObsoleteProjects($allSyncedProofhubProjectIds->unique());
    }

    /**
     * Syncs user assignments for a specific project.
     *
     * @param  Project  $project  The project model.
     * @param  array  $assignedUserIds  Array of ProofHub user IDs assigned to the project.
     */
    private function syncProjectUsers(
        Project $project,
        array $assignedUserIds
    ): void {
        // Find local user IDs corresponding to the trackable ProofHub users
        $localUserIds = User::whereIn('proofhub_id', $assignedUserIds)
            ->trackable()
            ->pluck('id');
        // Log missing users
        $missingUserIds = array_diff($assignedUserIds, User::whereIn('proofhub_id', $assignedUserIds)->pluck('proofhub_id')->toArray());
        if (! empty($missingUserIds)) {
            foreach ($missingUserIds as $missingId) {
                $this->processAssignedUser($missingId, $project->proofhub_project_id);
            }
        }
        // Efficiently sync the relationships
        $project->users()->sync($localUserIds);
    }

    private function processAssignedUser($assignedUser, $projectId): void
    {
        if (! $assignedUser) {
            Log::warning(class_basename(static::class).' ProofHub: Assigned user not found locally for project', [
                'job' => class_basename(static::class),
                'entity' => 'user',
                'project_id' => $projectId,
                'assigned_user' => $assignedUser,
            ]);
        }
    }

    /**
     * Removes local projects that no longer exist in ProofHub.
     *
     * @param  Collection  $syncedProjectIds  All unique ProofHub project IDs found during the sync.
     */
    private function removeObsoleteProjects(Collection $syncedProjectIds): void
    {
        if ($syncedProjectIds->isEmpty()) {
            Log::info(
                'No ProofHub projects found during sync, skipping obsolete project cleanup.'
            );

            return;
        }

        // Find local project IDs that were not in the synced list
        $obsoleteProjectIds = Project::whereNotIn(
            'proofhub_project_id',
            $syncedProjectIds
        )->pluck('proofhub_project_id');

        if ($obsoleteProjectIds->isEmpty()) {
            Log::info('No obsolete ProofHub projects to delete.');

            return;
        }

        Log::info(
            "Deleting {$obsoleteProjectIds->count()} obsolete ProofHub projects.",
            [
                'ids_to_delete' => $obsoleteProjectIds->all(),
            ]
        );

        // Delete the obsolete projects - uses individual delete to trigger model events
        Project::whereIn('proofhub_project_id', $obsoleteProjectIds)
            ->with(['users', 'tasks.timeEntries', 'timeEntries']) // Eager load for observer
            ->get()
            ->each(fn (Project $p) => $p->delete());
    }
}
