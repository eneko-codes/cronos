<?php

declare(strict_types=1);

namespace App\Actions\Proofhub;

use App\DataTransferObjects\Proofhub\ProofhubTaskDTO;
use App\Enums\Platform;
use App\Models\Task;
use App\Models\UserExternalIdentity;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ProcessProofhubTaskAction
{
    public function execute(ProofhubTaskDTO $taskDto): void
    {
        try {
            Validator::make(
                [
                    'id' => $taskDto->id,
                    'title' => $taskDto->title,
                ],
                [
                    'id' => ['required', 'integer'],
                    'title' => ['required', 'string'],
                ]
            )->validate();

            $task = Task::updateOrCreate(
                ['proofhub_task_id' => $taskDto->id],
                [
                    'proofhub_project_id' => $taskDto->project['id'] ?? $taskDto->project_id,
                    'title' => $taskDto->title,
                    'status' => $taskDto->stage['name'] ?? null,
                    'due_date' => $taskDto->due_date,
                    'description' => $taskDto->description,
                    'tags' => $taskDto->tags,
                    'proofhub_creator_id' => $taskDto->creator['id'] ?? null,
                    'proofhub_created_at' => $taskDto->proofhub_created_at,
                    'proofhub_updated_at' => $taskDto->proofhub_updated_at,
                ]
            );

            $this->syncTaskUsers($task, $taskDto->assigned ?? []);
        } catch (ValidationException $e) {
            Log::warning('Skipping ProofHub task due to validation failure.', [
                'task_id' => $taskDto->id,
                'errors' => $e->errors(),
            ]);
        }
    }

    private function syncTaskUsers(Task $task, array $assignedProofhubIds): void
    {
        $proofhubIds = collect($assignedProofhubIds)->filter()->unique()->map(fn ($id) => (string) $id);

        // Find users by their ProofHub external identities
        $userIds = UserExternalIdentity::where('platform', Platform::ProofHub)
            ->whereIn('external_id', $proofhubIds)
            ->pluck('user_id');

        $task->users()->sync($userIds);
    }
}
