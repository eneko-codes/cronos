<?php

declare(strict_types=1);

use App\DataTransferObjects\Odoo\OdooLeaveTypeDTO;

test('OdooLeaveTypeDTO can be instantiated with all properties', function (): void {
    $dto = new OdooLeaveTypeDTO(
        id: 1,
        name: 'Paid Time Off',
        request_unit: 'day',
        active: true,
        create_date: '2024-01-01 09:00:00',
        write_date: '2024-01-15 14:30:00'
    );

    expect($dto)
        ->toBeInstanceOf(OdooLeaveTypeDTO::class)
        ->toHaveProperty('id', 1)
        ->toHaveProperty('name', 'Paid Time Off')
        ->toHaveProperty('request_unit', 'day')
        ->toHaveProperty('active', true)
        ->toHaveProperty('create_date', '2024-01-01 09:00:00')
        ->toHaveProperty('write_date', '2024-01-15 14:30:00');
});

test('OdooLeaveTypeDTO can be instantiated with minimal properties', function (): void {
    $dto = new OdooLeaveTypeDTO;

    expect($dto)
        ->toBeInstanceOf(OdooLeaveTypeDTO::class)
        ->toHaveProperty('id', null)
        ->toHaveProperty('name', null)
        ->toHaveProperty('request_unit', null)
        ->toHaveProperty('active', null)
        ->toHaveProperty('create_date', null)
        ->toHaveProperty('write_date', null);
});

test('OdooLeaveTypeDTO handles null and false values correctly', function (): void {
    // Simulating how OdooApiClient would create the DTO with null conversions
    $dto = new OdooLeaveTypeDTO(
        id: 2,
        name: 'Sick Leave',
        request_unit: null, // converted from false
        active: false,
        create_date: '2024-01-01 09:00:00',
        write_date: null // converted from false
    );

    expect($dto)
        ->toBeInstanceOf(OdooLeaveTypeDTO::class)
        ->toHaveProperty('id', 2)
        ->toHaveProperty('name', 'Sick Leave')
        ->toHaveProperty('request_unit', null)
        ->toHaveProperty('active', false)
        ->toHaveProperty('create_date', '2024-01-01 09:00:00')
        ->toHaveProperty('write_date', null);
});

test('OdooLeaveTypeDTO is readonly', function (): void {
    $dto = new OdooLeaveTypeDTO(id: 1, name: 'Test Leave Type');

    // Verify the class is readonly by checking it's a readonly class
    $reflection = new ReflectionClass($dto);
    expect($reflection->isReadOnly())->toBeTrue();
});

test('OdooLeaveTypeDTO handles different request units', function (): void {
    $dayDto = new OdooLeaveTypeDTO(request_unit: 'day');
    $halfDayDto = new OdooLeaveTypeDTO(request_unit: 'half_day');
    $hourDto = new OdooLeaveTypeDTO(request_unit: 'hour');
    $nullDto = new OdooLeaveTypeDTO(request_unit: null);

    expect($dayDto->request_unit)->toBe('day');
    expect($halfDayDto->request_unit)->toBe('half_day');
    expect($hourDto->request_unit)->toBe('hour');
    expect($nullDto->request_unit)->toBeNull();
});

test('OdooLeaveTypeDTO handles datetime strings correctly', function (): void {
    $dto = new OdooLeaveTypeDTO(
        create_date: '2024-01-01 09:00:00',
        write_date: '2024-01-15 14:30:00'
    );

    expect($dto->create_date)->toBe('2024-01-01 09:00:00');
    expect($dto->write_date)->toBe('2024-01-15 14:30:00');

    // Test with null datetime values
    $nullDto = new OdooLeaveTypeDTO(
        create_date: null,
        write_date: null
    );

    expect($nullDto->create_date)->toBeNull();
    expect($nullDto->write_date)->toBeNull();
});

test('OdooLeaveTypeDTO handles various active states', function (): void {
    $activeDto = new OdooLeaveTypeDTO(active: true);
    $inactiveDto = new OdooLeaveTypeDTO(active: false);
    $nullDto = new OdooLeaveTypeDTO(active: null);

    expect($activeDto->active)->toBe(true);
    expect($inactiveDto->active)->toBe(false);
    expect($nullDto->active)->toBeNull();
});

test('OdooLeaveTypeDTO handles different leave type names', function (): void {
    $ptoDto = new OdooLeaveTypeDTO(name: 'Paid Time Off');
    $sickDto = new OdooLeaveTypeDTO(name: 'Sick Leave');
    $personalDto = new OdooLeaveTypeDTO(name: 'Personal Leave');
    $nullDto = new OdooLeaveTypeDTO(name: null);

    expect($ptoDto->name)->toBe('Paid Time Off');
    expect($sickDto->name)->toBe('Sick Leave');
    expect($personalDto->name)->toBe('Personal Leave');
    expect($nullDto->name)->toBeNull();
});
