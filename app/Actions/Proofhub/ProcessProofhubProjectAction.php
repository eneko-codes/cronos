<?php

declare(strict_types=1);

namespace App\Actions\Proofhub;

use App\DataTransferObjects\Proofhub\ProofhubProjectDTO;
use App\Enums\Platform;
use App\Models\Project;
use App\Models\UserExternalIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Action to synchronize ProofHub project data with the local projects table.
 */
final class ProcessProofhubProjectAction
{
    /**
     * The ProofHub project data transfer object.
     */
    private ProofhubProjectDTO $dto;

    /**
     * Synchronizes a single ProofHub project DTO with the local database.
     *
     * @param  ProofhubProjectDTO  $projectDto  The DTO to process.
     */
    public function execute(ProofhubProjectDTO $projectDto): void
    {
        $this->dto = $projectDto;

        $validator = Validator::make(
            [
                'id' => $this->dto->id,
                'title' => $this->dto->title,
            ],
            [
                'id' => 'required|integer',
                'title' => 'required|string',
            ],
            [
                'id.required' => 'ProofHub project is missing an ID.',
                'title.required' => 'ProofHub project (ID: '.$this->dto->id.') is missing a title.',
            ]
        );

        if ($validator->fails()) {
            Log::warning('Skipping ProofHub project due to validation failure.', [
                'project_id' => $this->dto->id,
                'errors' => $validator->errors()->all(),
            ]);

            return;
        }

        DB::transaction(function (): void {
            $creatorId = $this->dto->creator['id'] ?? null;
            $managerId = $this->dto->manager['id'] ?? null;

            $project = Project::updateOrCreate(
                ['proofhub_project_id' => $this->dto->id],
                [
                    'title' => $this->dto->title,
                    'status' => $this->dto->status['name'] ?? null,
                    'description' => $this->dto->description,
                    'proofhub_created_at' => $this->dto->proofhub_created_at,
                    'proofhub_updated_at' => $this->dto->proofhub_updated_at,
                    'proofhub_creator_id' => $creatorId,
                    'proofhub_manager_id' => $managerId,
                ]
            );

            $this->syncProjectUsers($project);
        });
    }

    /**
     * Sync the project's assigned users (many-to-many).
     *
     * @param  Project  $project  The local project model.
     */
    private function syncProjectUsers(Project $project): void
    {
        $assignedProofhubIds = collect($this->dto->assigned)->filter()->unique()->map(fn ($id) => (string) $id);

        // Find users by their ProofHub external identities
        $userIds = UserExternalIdentity::where('platform', Platform::ProofHub)
            ->whereIn('external_id', $assignedProofhubIds)
            ->pluck('user_id');

        $project->users()->sync($userIds);
    }
}
