<?php

declare(strict_types=1);

use App\DataTransferObjects\Proofhub\ProofhubTaskDTO;

test('ProofhubTaskDTO hydration from API response', function (): void {
    $apiResponse = [
        'id' => 789,
        'name' => 'Task 1',
        'project_id' => 456,
        'project' => ['id' => 456, 'name' => 'Project X'],
        'assigned' => [123],
        'title' => 'Task Title',
        'subtasks' => [],
        'status' => 'open',
        'due_date' => '2024-07-01',
        'description' => 'A task',
        'tags' => ['urgent', 'backend'],
        'priority' => 'high',
        'created_by' => 'Jane Doe',
        'updated_by' => 'John Smith',
        'proofhub_created_at' => '2024-06-01T10:00:00Z',
        'proofhub_updated_at' => '2024-06-10T10:00:00Z',
    ];
    $dto = new ProofhubTaskDTO(
        $apiResponse['id'],
        $apiResponse['name'],
        $apiResponse['project_id'],
        $apiResponse['project'],
        $apiResponse['assigned'],
        $apiResponse['title'],
        $apiResponse['subtasks'],
        $apiResponse['status'],
        $apiResponse['due_date'],
        $apiResponse['description'],
        $apiResponse['tags'],
        $apiResponse['priority'],
        $apiResponse['created_by'],
        $apiResponse['updated_by'],
        $apiResponse['proofhub_created_at'],
        $apiResponse['proofhub_updated_at']
    );
    expect($dto->id)->toBe(789)
        ->and($dto->tags)->toBe(['urgent', 'backend'])
        ->and($dto->priority)->toBe('high');
});

test('ProofhubTaskDTO can be constructed with all fields null', function (): void {
    $dto = new App\DataTransferObjects\Proofhub\ProofhubTaskDTO;
    expect($dto->id)->toBeNull();
    expect($dto->name)->toBeNull();
    expect($dto->project_id)->toBeNull();
    expect($dto->project)->toBeNull();
    expect($dto->assigned)->toBeNull();
    expect($dto->title)->toBeNull();
    expect($dto->subtasks)->toBeNull();
    expect($dto->status)->toBeNull();
    expect($dto->due_date)->toBeNull();
    expect($dto->description)->toBeNull();
    expect($dto->tags)->toBeNull();
    expect($dto->priority)->toBeNull();
    expect($dto->created_by)->toBeNull();
    expect($dto->updated_by)->toBeNull();
    expect($dto->proofhub_created_at)->toBeNull();
    expect($dto->proofhub_updated_at)->toBeNull();
});
