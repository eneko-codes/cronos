<?php

declare(strict_types=1);

use App\DataTransferObjects\Odoo\OdooCategoryDTO;

test('OdooCategoryDTO can be instantiated with all properties', function (): void {
    $dto = new OdooCategoryDTO(
        id: 1,
        name: 'Full Time',
        active: true
    );

    expect($dto)
        ->toBeInstanceOf(OdooCategoryDTO::class)
        ->toHaveProperty('id', 1)
        ->toHaveProperty('name', 'Full Time')
        ->toHaveProperty('active', true);
});

test('OdooCategoryDTO can be instantiated with minimal properties', function (): void {
    $dto = new OdooCategoryDTO;

    expect($dto)
        ->toBeInstanceOf(OdooCategoryDTO::class)
        ->toHaveProperty('id', null)
        ->toHaveProperty('name', null)
        ->toHaveProperty('active', null);
});

test('OdooCategoryDTO handles null and false values correctly', function (): void {
    // Simulating how OdooApiClient would create the DTO
    $dto = new OdooCategoryDTO(
        id: 2,
        name: 'Part Time',
        active: false
    );

    expect($dto)
        ->toBeInstanceOf(OdooCategoryDTO::class)
        ->toHaveProperty('id', 2)
        ->toHaveProperty('name', 'Part Time')
        ->toHaveProperty('active', false);
});

test('OdooCategoryDTO is readonly', function (): void {
    $dto = new OdooCategoryDTO(id: 1, name: 'Test Category');

    // Verify the class is readonly by checking it's a readonly class
    $reflection = new ReflectionClass($dto);
    expect($reflection->isReadOnly())->toBeTrue();
});

test('OdooCategoryDTO handles various active states', function (): void {
    $activeDto = new OdooCategoryDTO(active: true);
    $inactiveDto = new OdooCategoryDTO(active: false);
    $nullDto = new OdooCategoryDTO(active: null);

    expect($activeDto->active)->toBe(true);
    expect($inactiveDto->active)->toBe(false);
    expect($nullDto->active)->toBeNull();
});

test('OdooCategoryDTO handles different name types', function (): void {
    $stringNameDto = new OdooCategoryDTO(name: 'Full Time Employee');
    $nullNameDto = new OdooCategoryDTO(name: null);

    expect($stringNameDto->name)->toBe('Full Time Employee');
    expect($nullNameDto->name)->toBeNull();
});
