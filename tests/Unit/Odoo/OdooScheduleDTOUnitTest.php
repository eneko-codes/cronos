<?php

declare(strict_types=1);

use App\DataTransferObjects\Odoo\OdooScheduleDTO;

test('OdooScheduleDTO can be instantiated with all properties', function (): void {
    $dto = new OdooScheduleDTO(
        id: 1,
        name: 'Standard 40h',
        active: true,
        attendance_ids: [1, 2, 3, 4, 5],
        hours_per_day: 8.0,
        two_weeks_calendar: true,
        two_weeks_explanation: 'Alternating week schedule',
        flexible_hours: true,
        create_date: '2024-01-01 09:00:00',
        write_date: '2024-01-10 09:00:00'
    );

    expect($dto)
        ->toBeInstanceOf(OdooScheduleDTO::class)
        ->toHaveProperty('id', 1)
        ->toHaveProperty('name', 'Standard 40h')
        ->toHaveProperty('active', true)
        ->toHaveProperty('attendance_ids', [1, 2, 3, 4, 5])
        ->toHaveProperty('hours_per_day', 8.0)
        ->toHaveProperty('two_weeks_calendar', true)
        ->toHaveProperty('two_weeks_explanation', 'Alternating week schedule')
        ->toHaveProperty('flexible_hours', true)
        ->toHaveProperty('create_date', '2024-01-01 09:00:00')
        ->toHaveProperty('write_date', '2024-01-10 09:00:00');
});

test('OdooScheduleDTO can be instantiated with minimal properties', function (): void {
    $dto = new OdooScheduleDTO;

    expect($dto)
        ->toBeInstanceOf(OdooScheduleDTO::class)
        ->toHaveProperty('id', null)
        ->toHaveProperty('name', null)
        ->toHaveProperty('active', null)
        ->toHaveProperty('attendance_ids', [])
        ->toHaveProperty('hours_per_day', null)
        ->toHaveProperty('two_weeks_calendar', null)
        ->toHaveProperty('two_weeks_explanation', null)
        ->toHaveProperty('flexible_hours', null)
        ->toHaveProperty('create_date', null)
        ->toHaveProperty('write_date', null);
});

test('OdooScheduleDTO handles null and false values correctly', function (): void {
    // Simulating how OdooApiClient would create the DTO with null conversions
    $dto = new OdooScheduleDTO(
        id: 2,
        name: 'Flexible 35h',
        active: false,
        attendance_ids: [6, 7, 8],
        hours_per_day: 7.0,
        two_weeks_calendar: null, // not set in Odoo
        two_weeks_explanation: null, // not set in Odoo
        flexible_hours: null, // not set in Odoo
        create_date: '2024-01-01 09:00:00',
        write_date: null // converted from false
    );

    expect($dto)
        ->toBeInstanceOf(OdooScheduleDTO::class)
        ->toHaveProperty('id', 2)
        ->toHaveProperty('name', 'Flexible 35h')
        ->toHaveProperty('active', false)
        ->toHaveProperty('attendance_ids', [6, 7, 8])
        ->toHaveProperty('hours_per_day', 7.0)
        ->toHaveProperty('two_weeks_calendar', null)
        ->toHaveProperty('two_weeks_explanation', null)
        ->toHaveProperty('flexible_hours', null)
        ->toHaveProperty('write_date', null);
});

test('OdooScheduleDTO is readonly', function (): void {
    $dto = new OdooScheduleDTO(id: 1, name: 'Test Schedule');

    // Verify the class is readonly by checking it's a readonly class
    $reflection = new ReflectionClass($dto);
    expect($reflection->isReadOnly())->toBeTrue();
});

test('OdooScheduleDTO attendance_ids is always an array', function (): void {
    $dtoWithAttendance = new OdooScheduleDTO(attendance_ids: [1, 2, 3]);
    $dtoEmptyAttendance = new OdooScheduleDTO(attendance_ids: []);
    $dtoDefaultAttendance = new OdooScheduleDTO;

    expect($dtoWithAttendance->attendance_ids)
        ->toBeArray()
        ->toHaveCount(3)
        ->toContain(1, 2, 3);

    expect($dtoEmptyAttendance->attendance_ids)
        ->toBeArray()
        ->toBeEmpty();

    expect($dtoDefaultAttendance->attendance_ids)
        ->toBeArray()
        ->toBeEmpty();
});

test('OdooScheduleDTO handles float values correctly', function (): void {
    $dto = new OdooScheduleDTO(hours_per_day: 8.5);

    expect($dto->hours_per_day)->toBe(8.5);

    // Test with null float value
    $nullDto = new OdooScheduleDTO(hours_per_day: null);

    expect($nullDto->hours_per_day)->toBeNull();
});

test('OdooScheduleDTO handles optional bi-weekly fields', function (): void {
    // Test with bi-weekly calendar enabled
    $biWeeklyDto = new OdooScheduleDTO(
        two_weeks_calendar: true,
        two_weeks_explanation: 'Week 1: Mon-Wed, Week 2: Thu-Fri'
    );

    expect($biWeeklyDto->two_weeks_calendar)->toBe(true);
    expect($biWeeklyDto->two_weeks_explanation)->toBe('Week 1: Mon-Wed, Week 2: Thu-Fri');

    // Test with bi-weekly calendar disabled
    $normalDto = new OdooScheduleDTO(
        two_weeks_calendar: false,
        two_weeks_explanation: null
    );

    expect($normalDto->two_weeks_calendar)->toBe(false);
    expect($normalDto->two_weeks_explanation)->toBeNull();

    // Test with bi-weekly fields not set
    $notSetDto = new OdooScheduleDTO(
        two_weeks_calendar: null,
        two_weeks_explanation: null
    );

    expect($notSetDto->two_weeks_calendar)->toBeNull();
    expect($notSetDto->two_weeks_explanation)->toBeNull();
});

test('OdooScheduleDTO handles flexible hours field', function (): void {
    $flexibleDto = new OdooScheduleDTO(flexible_hours: true);
    $rigidDto = new OdooScheduleDTO(flexible_hours: false);
    $notSetDto = new OdooScheduleDTO(flexible_hours: null);

    expect($flexibleDto->flexible_hours)->toBe(true);
    expect($rigidDto->flexible_hours)->toBe(false);
    expect($notSetDto->flexible_hours)->toBeNull();
});

test('OdooScheduleDTO handles datetime strings correctly', function (): void {
    $dto = new OdooScheduleDTO(
        create_date: '2024-01-01 09:00:00',
        write_date: '2024-01-10 09:00:00'
    );

    expect($dto->create_date)->toBe('2024-01-01 09:00:00');
    expect($dto->write_date)->toBe('2024-01-10 09:00:00');

    // Test with null datetime values
    $nullDto = new OdooScheduleDTO(
        create_date: null,
        write_date: null
    );

    expect($nullDto->create_date)->toBeNull();
    expect($nullDto->write_date)->toBeNull();
});

test('OdooScheduleDTO handles various active states', function (): void {
    $activeDto = new OdooScheduleDTO(active: true);
    $inactiveDto = new OdooScheduleDTO(active: false);
    $nullDto = new OdooScheduleDTO(active: null);

    expect($activeDto->active)->toBe(true);
    expect($inactiveDto->active)->toBe(false);
    expect($nullDto->active)->toBeNull();
});
