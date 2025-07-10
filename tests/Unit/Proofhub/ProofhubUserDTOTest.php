<?php

declare(strict_types=1);

use App\DataTransferObjects\Proofhub\ProofhubUserDTO;

test('ProofhubUserDTO hydration from API response', function (): void {
    $apiResponse = [
        'id' => 123,
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'verified' => '2',
        'groups' => [1504559028],
        'timezone' => 59,
        'initials' => 'JD',
        'profile_color' => '#d2d24b',
        'image_url' => 'https://...',
        'language' => 'en',
        'suspended' => false,
        'last_active' => '2024-06-28T12:35:27+00:00',
        'role' => ['id' => 1, 'name' => 'Admin'],
        'proofhub_created_at' => '2024-06-01T10:00:00Z',
        'proofhub_updated_at' => '2024-06-10T10:00:00Z',
    ];
    $dto = new ProofhubUserDTO(
        $apiResponse['id'],
        $apiResponse['email'],
        $apiResponse['name'],
        $apiResponse['verified'],
        $apiResponse['groups'],
        $apiResponse['timezone'],
        $apiResponse['initials'],
        $apiResponse['profile_color'],
        $apiResponse['image_url'],
        $apiResponse['language'],
        $apiResponse['suspended'],
        $apiResponse['last_active'],
        $apiResponse['role'],
        $apiResponse['proofhub_created_at'],
        $apiResponse['proofhub_updated_at']
    );
    expect($dto->id)->toBe(123)
        ->and($dto->name)->toBe('Jane Doe')
        ->and($dto->groups)->toBe([1504559028])
        ->and($dto->role)->toBe(['id' => 1, 'name' => 'Admin']);
});

test('ProofhubUserDTO can be constructed with all fields null', function (): void {
    $dto = new App\DataTransferObjects\Proofhub\ProofhubUserDTO;
    expect($dto->id)->toBeNull();
    expect($dto->email)->toBeNull();
    expect($dto->name)->toBeNull();
    expect($dto->verified)->toBeNull();
    expect($dto->groups)->toBeNull();
    expect($dto->timezone)->toBeNull();
    expect($dto->initials)->toBeNull();
    expect($dto->profile_color)->toBeNull();
    expect($dto->image_url)->toBeNull();
    expect($dto->language)->toBeNull();
    expect($dto->suspended)->toBeNull();
    expect($dto->last_active)->toBeNull();
    expect($dto->role)->toBeNull();
    expect($dto->proofhub_created_at)->toBeNull();
    expect($dto->proofhub_updated_at)->toBeNull();
});
