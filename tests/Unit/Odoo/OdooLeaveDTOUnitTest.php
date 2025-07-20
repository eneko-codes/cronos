<?php

declare(strict_types=1);

use App\DataTransferObjects\Odoo\OdooLeaveDTO;

test('OdooLeaveDTO can be instantiated with all properties', function (): void {
    $dto = new OdooLeaveDTO(
        id: 10,
        holiday_type: 'employee',
        date_from: '2024-07-01 09:00:00',
        date_to: '2024-07-05 18:00:00',
        number_of_days: 5.0,
        state: 'validate',
        holiday_status_id: [1, 'Paid Time Off'],
        request_hour_from: 9.0,
        request_hour_to: 18.0,
        employee_id: [1, 'John Doe'],
        category_id: [2, 'Full Time'],
        department_id: [3, 'Engineering']
    );

    expect($dto)
        ->toBeInstanceOf(OdooLeaveDTO::class)
        ->toHaveProperty('id', 10)
        ->toHaveProperty('holiday_type', 'employee')
        ->toHaveProperty('date_from', '2024-07-01 09:00:00')
        ->toHaveProperty('date_to', '2024-07-05 18:00:00')
        ->toHaveProperty('number_of_days', 5.0)
        ->toHaveProperty('state', 'validate')
        ->toHaveProperty('holiday_status_id', [1, 'Paid Time Off'])
        ->toHaveProperty('request_hour_from', 9.0)
        ->toHaveProperty('request_hour_to', 18.0)
        ->toHaveProperty('employee_id', [1, 'John Doe'])
        ->toHaveProperty('category_id', [2, 'Full Time'])
        ->toHaveProperty('department_id', [3, 'Engineering']);
});

test('OdooLeaveDTO can be instantiated with minimal properties', function (): void {
    $dto = new OdooLeaveDTO;

    expect($dto)
        ->toBeInstanceOf(OdooLeaveDTO::class)
        ->toHaveProperty('id', null)
        ->toHaveProperty('holiday_type', null)
        ->toHaveProperty('date_from', null)
        ->toHaveProperty('date_to', null)
        ->toHaveProperty('number_of_days', null)
        ->toHaveProperty('state', null)
        ->toHaveProperty('holiday_status_id', null)
        ->toHaveProperty('request_hour_from', null)
        ->toHaveProperty('request_hour_to', null)
        ->toHaveProperty('employee_id', null)
        ->toHaveProperty('category_id', null)
        ->toHaveProperty('department_id', null);
});

test('OdooLeaveDTO handles null and false values correctly', function (): void {
    // Simulating how OdooApiClient would create the DTO with null conversions
    $dto = new OdooLeaveDTO(
        id: 11,
        holiday_type: 'category',
        date_from: '2024-07-15 14:00:00',
        date_to: '2024-07-15 18:00:00',
        number_of_days: 0.5,
        state: 'confirm',
        holiday_status_id: [2, 'Sick Leave'],
        request_hour_from: 14.0,
        request_hour_to: 18.0,
        employee_id: null, // converted from false
        category_id: [1, 'Full Time'],
        department_id: null // converted from false
    );

    expect($dto)
        ->toBeInstanceOf(OdooLeaveDTO::class)
        ->toHaveProperty('id', 11)
        ->toHaveProperty('holiday_type', 'category')
        ->toHaveProperty('number_of_days', 0.5)
        ->toHaveProperty('employee_id', null)
        ->toHaveProperty('department_id', null);
});

test('OdooLeaveDTO is readonly', function (): void {
    $dto = new OdooLeaveDTO(id: 1, holiday_type: 'employee');

    // Verify the class is readonly by checking it's a readonly class
    $reflection = new ReflectionClass($dto);
    expect($reflection->isReadOnly())->toBeTrue();
});

test('OdooLeaveDTO relation fields accept arrays or null', function (): void {
    // Test with valid relation arrays
    $dtoWithRelations = new OdooLeaveDTO(
        holiday_status_id: [1, 'Paid Time Off'],
        employee_id: [1, 'John Doe'],
        category_id: [2, 'Full Time'],
        department_id: [3, 'Engineering']
    );

    expect($dtoWithRelations->holiday_status_id)
        ->toBeArray()
        ->toHaveCount(2)
        ->toEqual([1, 'Paid Time Off']);

    expect($dtoWithRelations->employee_id)
        ->toBeArray()
        ->toHaveCount(2)
        ->toEqual([1, 'John Doe']);

    expect($dtoWithRelations->category_id)
        ->toBeArray()
        ->toHaveCount(2)
        ->toEqual([2, 'Full Time']);

    expect($dtoWithRelations->department_id)
        ->toBeArray()
        ->toHaveCount(2)
        ->toEqual([3, 'Engineering']);

    // Test with null values
    $dtoWithNulls = new OdooLeaveDTO(
        holiday_status_id: null,
        employee_id: null,
        category_id: null,
        department_id: null
    );

    expect($dtoWithNulls->holiday_status_id)->toBeNull();
    expect($dtoWithNulls->employee_id)->toBeNull();
    expect($dtoWithNulls->category_id)->toBeNull();
    expect($dtoWithNulls->department_id)->toBeNull();
});

test('OdooLeaveDTO handles float values correctly', function (): void {
    $dto = new OdooLeaveDTO(
        number_of_days: 2.5,
        request_hour_from: 9.25, // 9:15 AM
        request_hour_to: 17.75   // 5:45 PM
    );

    expect($dto->number_of_days)->toBe(2.5);
    expect($dto->request_hour_from)->toBe(9.25);
    expect($dto->request_hour_to)->toBe(17.75);

    // Test with null float values
    $nullDto = new OdooLeaveDTO(
        number_of_days: null,
        request_hour_from: null,
        request_hour_to: null
    );

    expect($nullDto->number_of_days)->toBeNull();
    expect($nullDto->request_hour_from)->toBeNull();
    expect($nullDto->request_hour_to)->toBeNull();
});

test('OdooLeaveDTO handles datetime strings correctly', function (): void {
    $dto = new OdooLeaveDTO(
        date_from: '2024-07-01 09:00:00',
        date_to: '2024-07-05 18:00:00'
    );

    expect($dto->date_from)->toBe('2024-07-01 09:00:00');
    expect($dto->date_to)->toBe('2024-07-05 18:00:00');

    // Test with null datetime values
    $nullDto = new OdooLeaveDTO(
        date_from: null,
        date_to: null
    );

    expect($nullDto->date_from)->toBeNull();
    expect($nullDto->date_to)->toBeNull();
});

test('OdooLeaveDTO handles different holiday types and states', function (): void {
    $employeeLeave = new OdooLeaveDTO(holiday_type: 'employee', state: 'validate');
    $categoryLeave = new OdooLeaveDTO(holiday_type: 'category', state: 'draft');
    $departmentLeave = new OdooLeaveDTO(holiday_type: 'department', state: 'confirm');

    expect($employeeLeave->holiday_type)->toBe('employee');
    expect($employeeLeave->state)->toBe('validate');

    expect($categoryLeave->holiday_type)->toBe('category');
    expect($categoryLeave->state)->toBe('draft');

    expect($departmentLeave->holiday_type)->toBe('department');
    expect($departmentLeave->state)->toBe('confirm');
});
