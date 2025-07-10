<?php

declare(strict_types=1);

use App\DataTransferObjects\Proofhub\ProofhubTimeEntryDTO;

test('ProofhubTimeEntryDTO hydration from API response', function (): void {
    $apiResponse = [
        'id' => 1011,
        'user_id' => 123,
        'project_id' => 456,
        'task_id' => 789,
        'duration' => 3600,
        'date' => '2024-07-01',
        'created_at' => '2024-07-01T10:00:00Z',
        'user_email' => 'jane@example.com',
        'task_title' => 'Task 1',
        'status' => 'completed',
        'description' => 'Worked on task',
        'proofhub_updated_at' => '2024-07-01T12:00:00Z',
        'billable' => true,
        'comments' => 'Good progress',
        'tags' => ['urgent'],
    ];
    $dto = new ProofhubTimeEntryDTO(
        $apiResponse['id'],
        $apiResponse['user_id'],
        $apiResponse['project_id'],
        $apiResponse['task_id'],
        $apiResponse['duration'],
        $apiResponse['date'],
        $apiResponse['created_at'],
        $apiResponse['user_email'],
        $apiResponse['task_title'],
        $apiResponse['status'],
        $apiResponse['description'],
        $apiResponse['proofhub_updated_at'],
        $apiResponse['billable'],
        $apiResponse['comments'],
        $apiResponse['tags']
    );
    expect($dto->id)->toBe(1011)
        ->and($dto->user_id)->toBe(123)
        ->and($dto->tags)->toBe(['urgent'])
        ->and($dto->billable)->toBeTrue();
});

test('ProofhubTimeEntryDTO can be constructed with all fields null', function (): void {
    $dto = new App\DataTransferObjects\Proofhub\ProofhubTimeEntryDTO;
    expect($dto->id)->toBeNull();
    expect($dto->user_id)->toBeNull();
    expect($dto->project_id)->toBeNull();
    expect($dto->task_id)->toBeNull();
    expect($dto->duration)->toBeNull();
    expect($dto->date)->toBeNull();
    expect($dto->created_at)->toBeNull();
    expect($dto->user_email)->toBeNull();
    expect($dto->task_title)->toBeNull();
    expect($dto->status)->toBeNull();
    expect($dto->description)->toBeNull();
    expect($dto->proofhub_updated_at)->toBeNull();
    expect($dto->billable)->toBeNull();
    expect($dto->comments)->toBeNull();
    expect($dto->tags)->toBeNull();
});
