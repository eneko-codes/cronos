<?php

declare(strict_types=1);

use App\DataTransferObjects\Proofhub\ProofhubProjectDTO;

test('ProofhubProjectDTO hydration from API response', function (): void {
    $apiResponse = [
        'id' => 456,
        'name' => 'Project X',
        'title' => 'Project X Title',
        'assigned' => [123],
        'status' => 'active',
        'description' => 'A project',
        'created_at' => '2024-06-01T10:00:00Z',
        'updated_at' => '2024-06-10T10:00:00Z',
        'owner_id' => 123,
        'proofhub_created_at' => '2024-06-01T10:00:00Z',
        'proofhub_updated_at' => '2024-06-10T10:00:00Z',
    ];
    $dto = new ProofhubProjectDTO(
        $apiResponse['id'],
        $apiResponse['name'],
        $apiResponse['title'],
        $apiResponse['assigned'],
        $apiResponse['status'],
        $apiResponse['description'],
        $apiResponse['created_at'],
        $apiResponse['updated_at'],
        $apiResponse['owner_id'],
        $apiResponse['proofhub_created_at'],
        $apiResponse['proofhub_updated_at']
    );
    expect($dto->id)->toBe(456)
        ->and($dto->assigned)->toBe([123])
        ->and($dto->owner_id)->toBe(123);
});

test('ProofhubProjectDTO can be constructed with all fields null', function (): void {
    $dto = new App\DataTransferObjects\Proofhub\ProofhubProjectDTO;
    expect($dto->id)->toBeNull();
    expect($dto->name)->toBeNull();
    expect($dto->title)->toBeNull();
    expect($dto->assigned)->toBeNull();
    expect($dto->status)->toBeNull();
    expect($dto->description)->toBeNull();
    expect($dto->created_at)->toBeNull();
    expect($dto->updated_at)->toBeNull();
    expect($dto->owner_id)->toBeNull();
    expect($dto->proofhub_created_at)->toBeNull();
    expect($dto->proofhub_updated_at)->toBeNull();
});
