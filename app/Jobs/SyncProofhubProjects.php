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
 * and invalidates the entire cache store upon completion.
 */
class SyncProofhubProjects extends BaseSyncJob
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
    $projects = $this->proofhub->getProjects();
    $syncedProjectIds = [];

    foreach ($projects as $projectData) {
      $projectId = data_get($projectData, 'id');
      if (!$projectId) {
        continue;
      }

      $syncedProjectIds[] = $projectId;

      $projectName = data_get($projectData, 'title');
      $assignedUserIds = data_get($projectData, 'assigned', []);

      // Upsert the project
      $project = Project::updateOrCreate(
        ['proofhub_project_id' => $projectId],
        ['name' => $projectName]
      );

      // Find local users for assigned ProofHub IDs
      $localUserIds = User::whereIn('proofhub_id', $assignedUserIds)
        ->where('do_not_track', false)
        ->pluck('id');

      // Manually detach any users not in $localUserIds
      $existingUserIds = $project->users()->pluck('user_id');
      $toDetach = $existingUserIds->diff($localUserIds);
      foreach ($toDetach as $userId) {
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
      }

      // Manually attach any new users
      $toAttach = $localUserIds->diff($existingUserIds);
      foreach ($toAttach as $userId) {
        $project->users()->attach($userId);
      }
    }

    // Delete local projects not present in ProofHub
    if (!empty($syncedProjectIds)) {
      $localProjectIds = Project::pluck('proofhub_project_id');
      $projectsToDelete = $localProjectIds->diff($syncedProjectIds);

      if ($projectsToDelete->isNotEmpty()) {
        Project::whereIn('proofhub_project_id', $projectsToDelete)
          ->get()
          ->each(fn(Project $p) => $p->delete());
      }
    }
  }
}
