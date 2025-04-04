<?php

namespace App\Jobs;

use App\Models\Project;
use App\Models\User;
use App\Services\ProofhubApiCalls;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Class SyncProofhubProjects
 *
 * Synchronizes projects from ProofHub into the local database,
 * including project details and user assignments.
 */
class SyncProofhubProjects extends BaseSyncJob
{
  /**
   * The priority of the job in the queue.
   * Lower numbers indicate higher priority.
   *
   * @var int
   */
  public int $priority = 2;

  /**
   * SyncProofhubProjects constructor.
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
   * 1. Fetches projects from ProofHub API
   * 2. Processes and syncs each valid project
   * 3. Removes local projects that no longer exist in ProofHub
   *
   * @throws Exception If any part of the synchronization process fails
   */
  protected function execute(): void
  {
    // Step 1: Fetch projects from ProofHub API
    $projects = $this->proofhub->getProjects();

    // Step 2: Process and sync each valid project
    $syncedProjectIds = $this->syncProjects($projects);

    // Step 3: Remove projects that no longer exist in ProofHub
    $this->removeObsoleteProjects($syncedProjectIds);
  }

  /**
   * Processes and syncs projects from ProofHub.
   *
   * @param Collection $projects Projects from ProofHub API
   * @return Collection Collection of synced project IDs
   */
  private function syncProjects(Collection $projects): Collection
  {
    return $projects
      ->filter(fn($projectData) => data_get($projectData, 'id'))
      ->map(function ($projectData) {
        $projectId = data_get($projectData, 'id');
        $projectName = data_get($projectData, 'title');
        $assignedUserIds = data_get($projectData, 'assigned', []);

        // Upsert the project
        $project = Project::updateOrCreate(
          ['proofhub_project_id' => $projectId],
          ['name' => $projectName]
        );

        // Sync user assignments
        $this->syncProjectUsers($project, $assignedUserIds);

        return $projectId;
      })
      ->values();
  }

  /**
   * Syncs user assignments for a project.
   *
   * @param Project $project The project to sync users for
   * @param array $assignedUserIds ProofHub user IDs assigned to the project
   * @return void
   */
  private function syncProjectUsers(
    Project $project,
    array $assignedUserIds
  ): void {
    // Find local users for assigned ProofHub IDs
    $localUserIds = User::whereIn('proofhub_id', $assignedUserIds)
      ->trackable()
      ->pluck('id');

    // Get existing project user IDs
    $existingUserIds = $project->users()->pluck('user_id');

    // Detach users no longer assigned to the project
    $this->detachRemovedUsers($project, $existingUserIds->diff($localUserIds));

    // Attach newly assigned users
    $this->attachNewUsers($project, $localUserIds->diff($existingUserIds));
  }

  /**
   * Detaches users that are no longer assigned to a project.
   *
   * @param Project $project The project to detach users from
   * @param Collection $userIdsToDetach User IDs to detach
   * @return void
   */
  private function detachRemovedUsers(
    Project $project,
    Collection $userIdsToDetach
  ): void {
    $userIdsToDetach->each(function ($userId) use ($project) {
      try {
        // Check if relationship exists before detaching
        if ($project->users()->where('user_id', $userId)->exists()) {
          $project->users()->detach($userId);
        }
      } catch (\Exception $e) {
        // Silently continue if detaching fails
        // This is intentional as we don't want user relationship issues
        // to prevent the rest of the project sync from completing
      }
    });
  }

  /**
   * Attaches newly assigned users to a project.
   *
   * @param Project $project The project to attach users to
   * @param Collection $userIdsToAttach User IDs to attach
   * @return void
   */
  private function attachNewUsers(
    Project $project,
    Collection $userIdsToAttach
  ): void {
    $userIdsToAttach->each(function ($userId) use ($project) {
      $project->users()->attach($userId);
    });
  }

  /**
   * Removes local projects that no longer exist in ProofHub.
   *
   * @param Collection $syncedProjectIds IDs of projects that exist in ProofHub
   * @return void
   */
  private function removeObsoleteProjects(Collection $syncedProjectIds): void
  {
    if ($syncedProjectIds->isEmpty()) {
      return;
    }

    Project::pluck('proofhub_project_id')
      ->diff($syncedProjectIds)
      ->pipe(function ($projectsToDelete) {
        if ($projectsToDelete->isEmpty()) {
          return;
        }

        Project::whereIn('proofhub_project_id', $projectsToDelete)
          ->get()
          ->each(fn(Project $p) => $p->delete());
      });
  }
}
