<?php

declare(strict_types=1);

namespace App\Actions\Proofhub;

use App\DataTransferObjects\Proofhub\ProofhubTimeEntryDTO;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ProcessProofhubTimeEntryAction
{
    public function execute(ProofhubTimeEntryDTO $timeEntryDto): void
    {
        try {
            Validator::make(
                [
                    'id' => $timeEntryDto->id,
                    'creator' => $timeEntryDto->creator,
                    'project' => $timeEntryDto->project,
                    'date' => $timeEntryDto->date,
                ],
                [
                    'id' => ['required', 'integer'],
                    'creator' => ['required', 'array'],
                    'project' => ['required', 'array'],
                    'date' => ['required', 'date'],
                ]
            )->validate();

            $user = User::where('proofhub_id', $timeEntryDto->creator['id'])->first();
            if (! $user) {
                Log::warning('Skipping time entry: User not found.', ['proofhub_user_id' => $timeEntryDto->creator['id']]);

                return;
            }

            $durationInSeconds = ($timeEntryDto->logged_hours * 3600) + ($timeEntryDto->logged_mins * 60);

            TimeEntry::updateOrCreate(
                ['proofhub_time_entry_id' => $timeEntryDto->id],
                [
                    'user_id' => $user->id,
                    'proofhub_project_id' => $timeEntryDto->project['id'],
                    'proofhub_task_id' => $timeEntryDto->task['task_id'] ?? null,
                    'status' => $timeEntryDto->status,
                    'description' => $timeEntryDto->description,
                    'date' => Carbon::parse($timeEntryDto->date)->toDateString(),
                    'duration_seconds' => $durationInSeconds,
                    'proofhub_created_at' => $timeEntryDto->created_at,
                ]
            );
        } catch (ValidationException $e) {
            Log::warning('Skipping ProofHub time entry due to validation failure.', [
                'time_entry_id' => $timeEntryDto->id,
                'errors' => $e->errors(),
            ]);
        }
    }
}
