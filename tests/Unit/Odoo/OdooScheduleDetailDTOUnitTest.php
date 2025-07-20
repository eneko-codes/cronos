<?php

declare(strict_types=1);

use App\DataTransferObjects\Odoo\OdooScheduleDetailDTO;

test('OdooScheduleDetailDTO can be instantiated with all properties', function (): void {
    $dto = new OdooScheduleDetailDTO(
        id: 1,
        calendar_id: [1, 'Standard 40h'],
        name: 'Monday Morning',
        dayofweek: '0',
        hour_from: 9.0,
        hour_to: 13.0,
        day_period: 'morning',
        week_type: 1,
        date_from: '2024-01-01',
        date_to: '2024-12-31',
        active: true,
        create_date: '2024-01-01 09:00:00',
        write_date: '2024-01-10 09:00:00'
    );

    expect($dto)
        ->toBeInstanceOf(OdooScheduleDetailDTO::class)
        ->toHaveProperty('id', 1)
        ->toHaveProperty('calendar_id', [1, 'Standard 40h'])
        ->toHaveProperty('name', 'Monday Morning')
        ->toHaveProperty('dayofweek', '0')
        ->toHaveProperty('hour_from', 9.0)
        ->toHaveProperty('hour_to', 13.0)
        ->toHaveProperty('day_period', 'morning')
        ->toHaveProperty('week_type', 1)
        ->toHaveProperty('date_from', '2024-01-01')
        ->toHaveProperty('date_to', '2024-12-31')
        ->toHaveProperty('active', true)
        ->toHaveProperty('create_date', '2024-01-01 09:00:00')
        ->toHaveProperty('write_date', '2024-01-10 09:00:00');
});

test('OdooScheduleDetailDTO can be instantiated with minimal properties', function (): void {
    $dto = new OdooScheduleDetailDTO;

    expect($dto)
        ->toBeInstanceOf(OdooScheduleDetailDTO::class)
        ->toHaveProperty('id', null)
        ->toHaveProperty('calendar_id', null)
        ->toHaveProperty('name', null)
        ->toHaveProperty('dayofweek', null)
        ->toHaveProperty('hour_from', null)
        ->toHaveProperty('hour_to', null)
        ->toHaveProperty('day_period', null)
        ->toHaveProperty('week_type', null)
        ->toHaveProperty('date_from', null)
        ->toHaveProperty('date_to', null)
        ->toHaveProperty('active', null)
        ->toHaveProperty('create_date', null)
        ->toHaveProperty('write_date', null);
});

test('OdooScheduleDetailDTO handles null and false values correctly', function (): void {
    // Simulating how OdooApiClient would create the DTO with null conversions
    $dto = new OdooScheduleDetailDTO(
        id: 2,
        calendar_id: [2, 'Bi-weekly Flexible'],
        name: null, // converted from false
        dayofweek: '1',
        hour_from: 8.0,
        hour_to: 16.0,
        day_period: null, // converted from false
        week_type: null, // not set in Odoo (not bi-weekly)
        date_from: '2024-01-01',
        date_to: null, // converted from false
        active: false,
        create_date: '2024-01-01 09:00:00',
        write_date: null // converted from false
    );

    expect($dto)
        ->toBeInstanceOf(OdooScheduleDetailDTO::class)
        ->toHaveProperty('id', 2)
        ->toHaveProperty('calendar_id', [2, 'Bi-weekly Flexible'])
        ->toHaveProperty('name', null)
        ->toHaveProperty('dayofweek', '1')
        ->toHaveProperty('hour_from', 8.0)
        ->toHaveProperty('hour_to', 16.0)
        ->toHaveProperty('day_period', null)
        ->toHaveProperty('week_type', null)
        ->toHaveProperty('date_from', '2024-01-01')
        ->toHaveProperty('date_to', null)
        ->toHaveProperty('active', false)
        ->toHaveProperty('write_date', null);
});

test('OdooScheduleDetailDTO is readonly', function (): void {
    $dto = new OdooScheduleDetailDTO(id: 1, dayofweek: '0');

    // Verify the class is readonly by checking it's a readonly class
    $reflection = new ReflectionClass($dto);
    expect($reflection->isReadOnly())->toBeTrue();
});

test('OdooScheduleDetailDTO calendar_id field accepts arrays or null', function (): void {
    // Test with valid relation array
    $dtoWithCalendar = new OdooScheduleDetailDTO(
        calendar_id: [1, 'Standard 40h']
    );

    expect($dtoWithCalendar->calendar_id)
        ->toBeArray()
        ->toHaveCount(2)
        ->toEqual([1, 'Standard 40h']);

    // Test with null value
    $dtoWithNull = new OdooScheduleDetailDTO(
        calendar_id: null
    );

    expect($dtoWithNull->calendar_id)->toBeNull();
});

test('OdooScheduleDetailDTO handles time fields correctly', function (): void {
    $dto = new OdooScheduleDetailDTO(
        hour_from: 9.25, // 9:15 AM
        hour_to: 17.75   // 5:45 PM
    );

    expect($dto->hour_from)->toBe(9.25);
    expect($dto->hour_to)->toBe(17.75);

    // Test with null time values
    $nullDto = new OdooScheduleDetailDTO(
        hour_from: null,
        hour_to: null
    );

    expect($nullDto->hour_from)->toBeNull();
    expect($nullDto->hour_to)->toBeNull();
});

test('OdooScheduleDetailDTO handles dayofweek values correctly', function (): void {
    $mondayDto = new OdooScheduleDetailDTO(dayofweek: '0');
    $tuesdayDto = new OdooScheduleDetailDTO(dayofweek: '1');
    $wednesdayDto = new OdooScheduleDetailDTO(dayofweek: '2');
    $thursdayDto = new OdooScheduleDetailDTO(dayofweek: '3');
    $fridayDto = new OdooScheduleDetailDTO(dayofweek: '4');
    $saturdayDto = new OdooScheduleDetailDTO(dayofweek: '5');
    $sundayDto = new OdooScheduleDetailDTO(dayofweek: '6');
    $nullDto = new OdooScheduleDetailDTO(dayofweek: null);

    expect($mondayDto->dayofweek)->toBe('0');
    expect($tuesdayDto->dayofweek)->toBe('1');
    expect($wednesdayDto->dayofweek)->toBe('2');
    expect($thursdayDto->dayofweek)->toBe('3');
    expect($fridayDto->dayofweek)->toBe('4');
    expect($saturdayDto->dayofweek)->toBe('5');
    expect($sundayDto->dayofweek)->toBe('6');
    expect($nullDto->dayofweek)->toBeNull();
});

test('OdooScheduleDetailDTO handles week_type for bi-weekly schedules', function (): void {
    $bothWeeksDto = new OdooScheduleDetailDTO(week_type: 0);
    $week1Dto = new OdooScheduleDetailDTO(week_type: 1);
    $week2Dto = new OdooScheduleDetailDTO(week_type: 2);
    $notSetDto = new OdooScheduleDetailDTO(week_type: null);

    expect($bothWeeksDto->week_type)->toBe(0);
    expect($week1Dto->week_type)->toBe(1);
    expect($week2Dto->week_type)->toBe(2);
    expect($notSetDto->week_type)->toBeNull();
});

test('OdooScheduleDetailDTO handles day_period values', function (): void {
    $morningDto = new OdooScheduleDetailDTO(day_period: 'morning');
    $afternoonDto = new OdooScheduleDetailDTO(day_period: 'afternoon');
    $nullDto = new OdooScheduleDetailDTO(day_period: null);

    expect($morningDto->day_period)->toBe('morning');
    expect($afternoonDto->day_period)->toBe('afternoon');
    expect($nullDto->day_period)->toBeNull();
});

test('OdooScheduleDetailDTO handles date strings correctly', function (): void {
    $dto = new OdooScheduleDetailDTO(
        date_from: '2024-01-01',
        date_to: '2024-12-31'
    );

    expect($dto->date_from)->toBe('2024-01-01');
    expect($dto->date_to)->toBe('2024-12-31');

    // Test with null date values
    $nullDto = new OdooScheduleDetailDTO(
        date_from: null,
        date_to: null
    );

    expect($nullDto->date_from)->toBeNull();
    expect($nullDto->date_to)->toBeNull();
});

test('OdooScheduleDetailDTO handles datetime strings correctly', function (): void {
    $dto = new OdooScheduleDetailDTO(
        create_date: '2024-01-01 09:00:00',
        write_date: '2024-01-15 14:30:00'
    );

    expect($dto->create_date)->toBe('2024-01-01 09:00:00');
    expect($dto->write_date)->toBe('2024-01-15 14:30:00');

    // Test with null datetime values
    $nullDto = new OdooScheduleDetailDTO(
        create_date: null,
        write_date: null
    );

    expect($nullDto->create_date)->toBeNull();
    expect($nullDto->write_date)->toBeNull();
});

test('OdooScheduleDetailDTO handles various active states', function (): void {
    $activeDto = new OdooScheduleDetailDTO(active: true);
    $inactiveDto = new OdooScheduleDetailDTO(active: false);
    $nullDto = new OdooScheduleDetailDTO(active: null);

    expect($activeDto->active)->toBe(true);
    expect($inactiveDto->active)->toBe(false);
    expect($nullDto->active)->toBeNull();
});
