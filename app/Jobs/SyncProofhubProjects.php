<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Project;
use App\Models\User;
use App\Services\ProofhubApiService;
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
     */
    public int $priority = 2;

    /**
     * SyncProofhubProjects constructor.
     *
     * @param  ProofhubApiService  $proofhub  An instance of the ProofhubApiService service.
     */
    public function __construct(ProofhubApiService $proofhub)
    {
        $this->proofhub = $proofhub;
    }

    /**
     * Executes the synchronization process page by page.
     *
     * This method performs the following operations:
     * 1. Loops through pages fetched from ProofHub API using callPage
     * 2. Processes projects from each page, updating local records and user assignments
     * 3. Collects all valid ProofHub project IDs encountered
     * 4. Removes local projects whose ProofHub IDs were not found in the sync
     *
     * @throws Exception If any part of the synchronization process fails
     */
    protected function execute(): void
    {
        $endpoint = 'projects';
        $allSyncedProofhubProjectIds = collect(); // Track all synced IDs
        $currentPage = 1;
        $totalPages = 1; // Initialize for fallback
        $nextPageUrl = null;

        $baseUrl = config('services.proofhub.company_url'); // Needed for initial URL
        if (! $baseUrl) {
            throw new Exception('ProofHub company URL not configured.');
        }
        $initialUrl = "https://{$baseUrl}.proofhub.com/api/v3/{$endpoint}"; // Initial URL

        Log::info('Starting ProofHub project sync.');

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
            $projectsOnPage = $pageResult['data'];
            $nextPageUrl = $pageResult['nextPageUrl'];
            $totalPagesFromHeader = $pageResult['totalPages'];

            // Check for empty page (after first page, using fallback)
            if (
                $projectsOnPage->isEmpty() &&
                $currentPage > 1 &&
                $nextPageUrl === null
            ) {
                Log::info(
                    "No more projects found on page {$currentPage} using fallback, ending sync.",
                    [
                        'endpoint' => $endpoint,
                    ]
                );
                break;
            }

            // Process projects on the current page
            $syncedIdsOnPage = $this->processProjectPage($projectsOnPage);
            $allSyncedProofhubProjectIds = $allSyncedProofhubProjectIds->merge(
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

        // Step 3: Remove projects that no longer exist in ProofHub
        $this->removeObsoleteProjects($allSyncedProofhubProjectIds->unique());

        Log::info('Finished ProofHub project sync.', [
            'total_projects_processed' => $allSyncedProofhubProjectIds->count(), // This might count duplicates if API returns them
            'unique_projects_found' => $allSyncedProofhubProjectIds
                ->unique()
                ->count(),
        ]);
    }

    /**
     * Processes a single page of project data.
     *
     * @param  Collection  $projectsPage  Projects from one API page
     * @return Collection Collection of synced ProofHub project IDs from this page
     */
    private function processProjectPage(Collection $projectsPage): Collection
    {
        return $projectsPage
            ->filter(fn ($projectData) => data_get($projectData, 'id')) // Ensure project has an ID
            ->map(function ($projectData) {
                $projectId = data_get($projectData, 'id');
                $projectName = data_get($projectData, 'title');
                $assignedUserIds = data_get($projectData, 'assigned', []); // Default to empty array

                // Upsert the project (create or update based on proofhub_project_id)
                $project = Project::updateOrCreate(
                    ['proofhub_project_id' => $projectId],
                    ['name' => $projectName]
                );

                // Sync user assignments for this project
                $this->syncProjectUsers($project, $assignedUserIds);

                return $projectId; // Return the ID of the processed project
            })
            ->values(); // Return a collection of the processed IDs
    }

    /**
     * Syncs user assignments for a specific project.
     *
     * @param  Project  $project  The project model
     * @param  array  $assignedUserIds  Array of ProofHub user IDs assigned to the project
     */
    private function syncProjectUsers(
        Project $project,
        array $assignedUserIds
    ): void {
        // Find local user IDs corresponding to the trackable ProofHub users
        $localUserIds = User::whereIn('proofhub_id', $assignedUserIds)
            ->trackable() // Ensure we only link trackable users
            ->pluck('id');

        // Efficiently sync the relationships
        // This will attach missing users and detach users not in $localUserIds
        $project->users()->sync($localUserIds);

        // Optional: Log the sync action details if needed
        // Log::debug('Synced users for project.', ['project_id' => $project->id, 'assigned_user_ids' => $localUserIds->all()]);
    }

    /**
     * Removes local projects that no longer exist in ProofHub.
     *
     * @param  Collection  $syncedProjectIds  All unique ProofHub project IDs found during the sync
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
            ->get()
            ->each(fn (Project $p) => $p->delete());
    }
}
