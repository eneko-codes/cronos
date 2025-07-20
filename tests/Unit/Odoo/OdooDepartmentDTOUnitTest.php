<?php

declare(strict_types=1);

use App\DataTransferObjects\Odoo\OdooDepartmentDTO;

test('OdooDepartmentDTO can be instantiated with all properties', function (): void {
    $dto = new OdooDepartmentDTO(
        id: 1,
        name: 'Engineering',
        active: true,
        manager_id: [5, 'Jane Manager'],
        parent_id: [10, 'Company']
    );

    expect($dto)
        ->toBeInstanceOf(OdooDepartmentDTO::class)
        ->toHaveProperty('id', 1)
        ->toHaveProperty('name', 'Engineering')
        ->toHaveProperty('active', true)
        ->toHaveProperty('manager_id', [5, 'Jane Manager'])
        ->toHaveProperty('parent_id', [10, 'Company']);
});

test('OdooDepartmentDTO can be instantiated with minimal properties', function (): void {
    $dto = new OdooDepartmentDTO;

    expect($dto)
        ->toBeInstanceOf(OdooDepartmentDTO::class)
        ->toHaveProperty('id', null)
        ->toHaveProperty('name', null)
        ->toHaveProperty('active', null)
        ->toHaveProperty('manager_id', null)
        ->toHaveProperty('parent_id', null);
});

test('OdooDepartmentDTO handles null and false values correctly', function (): void {
    // Simulating how OdooApiClient would create the DTO with null conversions
    $dto = new OdooDepartmentDTO(
        id: 2,
        name: 'Marketing',
        active: false,
        manager_id: null, // converted from false
        parent_id: null   // converted from false
    );

    expect($dto)
        ->toBeInstanceOf(OdooDepartmentDTO::class)
        ->toHaveProperty('id', 2)
        ->toHaveProperty('name', 'Marketing')
        ->toHaveProperty('active', false)
        ->toHaveProperty('manager_id', null)
        ->toHaveProperty('parent_id', null);
});

test('OdooDepartmentDTO is readonly', function (): void {
    $dto = new OdooDepartmentDTO(id: 1, name: 'Test Department');

    // Verify the class is readonly by checking it's a readonly class
    $reflection = new ReflectionClass($dto);
    expect($reflection->isReadOnly())->toBeTrue();
});

test('OdooDepartmentDTO relation fields accept arrays or null', function (): void {
    // Test with valid relation arrays
    $dtoWithRelations = new OdooDepartmentDTO(
        manager_id: [5, 'Jane Manager'],
        parent_id: [10, 'Company']
    );

    expect($dtoWithRelations->manager_id)
        ->toBeArray()
        ->toHaveCount(2)
        ->toEqual([5, 'Jane Manager']);

    expect($dtoWithRelations->parent_id)
        ->toBeArray()
        ->toHaveCount(2)
        ->toEqual([10, 'Company']);

    // Test with null values
    $dtoWithNulls = new OdooDepartmentDTO(
        manager_id: null,
        parent_id: null
    );

    expect($dtoWithNulls->manager_id)->toBeNull();
    expect($dtoWithNulls->parent_id)->toBeNull();
});

test('OdooDepartmentDTO handles various active states', function (): void {
    $activeDto = new OdooDepartmentDTO(active: true);
    $inactiveDto = new OdooDepartmentDTO(active: false);
    $nullDto = new OdooDepartmentDTO(active: null);

    expect($activeDto->active)->toBe(true);
    expect($inactiveDto->active)->toBe(false);
    expect($nullDto->active)->toBeNull();
});
