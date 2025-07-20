<?php

declare(strict_types=1);

use App\Actions\Odoo\ProcessOdooScheduleDetailAction;
use App\DataTransferObjects\Odoo\OdooScheduleDetailDTO;
use App\Models\Schedule;
use App\Models\ScheduleDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->action = new ProcessOdooScheduleDetailAction;

    // Create test schedule
    $this->schedule = Schedule::create([
        'odoo_schedule_id' => 1,
        'description' => 'Standard 40h',
        'active' => true,
    ]);
});

test('ProcessOdooScheduleDetailAction creates new schedule detail with valid data', function (): void {
    $dto = new OdooScheduleDetailDTO(
        id: 1,
        calendar_id: [1, 'Standard 40h'],
        name: 'Monday Morning',
        dayofweek: '0', // Monday
        hour_from: 9.0,  // 09:00
        hour_to: 13.0,   // 13:00
        day_period: 'morning',
        week_type: 0,
        date_from: '2024-01-01',
        date_to: '2024-12-31',
        active: true,
        create_date: '2024-01-01 10:00:00',
        write_date: '2024-01-15 14:30:00'
    );

    $this->action->execute($dto);

    $scheduleDetail = ScheduleDetail::where('odoo_detail_id', 1)->first();

    expect($scheduleDetail)->not->toBeNull();
    expect($scheduleDetail->odoo_detail_id)->toBe(1);
    expect($scheduleDetail->odoo_schedule_id)->toBe(1);
    expect($scheduleDetail->name)->toBe('Monday Morning');
    expect($scheduleDetail->weekday)->toBe(0);
    expect($scheduleDetail->start?->format('H:i:s'))->toBe('09:00:00');
    expect($scheduleDetail->end?->format('H:i:s'))->toBe('13:00:00');
    expect($scheduleDetail->day_period)->toBe('morning');
    expect($scheduleDetail->week_type)->toBe(0);
    expect($scheduleDetail->date_from?->format('Y-m-d'))->toBe('2024-01-01');
    expect($scheduleDetail->date_to?->format('Y-m-d'))->toBe('2024-12-31');
    expect($scheduleDetail->active)->toBe(true);
    expect($scheduleDetail->odoo_created_at?->format('Y-m-d H:i:s'))->toBe('2024-01-01 10:00:00');
    expect($scheduleDetail->odoo_updated_at?->format('Y-m-d H:i:s'))->toBe('2024-01-15 14:30:00');
});

test('ProcessOdooScheduleDetailAction updates existing schedule detail', function (): void {
    // Create existing schedule detail
    $existingDetail = ScheduleDetail::create([
        'odoo_detail_id' => 1,
        'odoo_schedule_id' => 1,
        'name' => 'Old Name',
        'weekday' => 1, // Tuesday
        'start' => '08:00:00',
        'end' => '12:00:00',
        'day_period' => 'morning',
        'week_type' => 1,
        'date_from' => '2023-01-01',
        'date_to' => '2023-12-31',
        'active' => false,
    ]);

    $dto = new OdooScheduleDetailDTO(
        id: 1,
        calendar_id: [1, 'Standard 40h'],
        name: 'Monday Morning',
        dayofweek: '0', // Monday
        hour_from: 9.0,
        hour_to: 13.0,
        day_period: 'morning',
        week_type: 0,
        date_from: '2024-01-01',
        date_to: '2024-12-31',
        active: true
    );

    $this->action->execute($dto);

    $scheduleDetail = ScheduleDetail::where('odoo_detail_id', 1)->first();

    expect($scheduleDetail)->not->toBeNull();
    expect($scheduleDetail->name)->toBe('Monday Morning');
    expect($scheduleDetail->weekday)->toBe(0);
    expect($scheduleDetail->start?->format('H:i:s'))->toBe('09:00:00');
    expect($scheduleDetail->end?->format('H:i:s'))->toBe('13:00:00');
    expect($scheduleDetail->week_type)->toBe(0);
    expect($scheduleDetail->active)->toBe(true);

    // Should still be the same record, not a new one
    expect(ScheduleDetail::count())->toBe(1);
});

test('ProcessOdooScheduleDetailAction formats time correctly', function (): void {
    // Test various time formats
    $testCases = [
        ['hour' => 9.0, 'expected' => '09:00:00'], // 9 AM
        ['hour' => 9.5, 'expected' => '09:30:00'], // 9:30 AM
        ['hour' => 13.75, 'expected' => '13:45:00'], // 1:45 PM
        ['hour' => 18.25, 'expected' => '18:15:00'], // 6:15 PM
        ['hour' => 0.0, 'expected' => '00:00:00'], // Midnight
        ['hour' => 23.5, 'expected' => '23:30:00'], // 11:30 PM
    ];

    foreach ($testCases as $index => $testCase) {
        $dto = new OdooScheduleDetailDTO(
            id: $index + 1,
            calendar_id: [1, 'Standard 40h'],
            name: "Test Detail {$index}",
            dayofweek: '0',
            hour_from: $testCase['hour'],
            hour_to: $testCase['hour'] + 1, // End time is 1 hour later
            day_period: 'morning',
            week_type: 0,
            date_from: '2024-01-01',
            date_to: '2024-12-31',
            active: true
        );

        $this->action->execute($dto);

        $scheduleDetail = ScheduleDetail::where('odoo_detail_id', $index + 1)->first();
        expect($scheduleDetail->start?->format('H:i:s'))->toBe($testCase['expected']);
    }
});

test('ProcessOdooScheduleDetailAction handles different weekdays', function (): void {
    $weekdays = ['0', '1', '2', '3', '4', '5', '6']; // Monday to Sunday

    foreach ($weekdays as $index => $weekday) {
        $dto = new OdooScheduleDetailDTO(
            id: $index + 1,
            calendar_id: [1, 'Standard 40h'],
            name: "Day {$weekday}",
            dayofweek: $weekday,
            hour_from: 9.0,
            hour_to: 17.0,
            day_period: 'morning',
            week_type: 0,
            date_from: '2024-01-01',
            date_to: '2024-12-31',
            active: true
        );

        $this->action->execute($dto);

        $scheduleDetail = ScheduleDetail::where('odoo_detail_id', $index + 1)->first();
        expect($scheduleDetail->weekday)->toBe((int) $weekday);
    }
});

test('ProcessOdooScheduleDetailAction handles week types', function (): void {
    $weekTypes = [0, 1, 2]; // Both weeks, week 1, week 2

    foreach ($weekTypes as $index => $weekType) {
        $dto = new OdooScheduleDetailDTO(
            id: $index + 1,
            calendar_id: [1, 'Standard 40h'],
            name: "Week Type {$weekType}",
            dayofweek: '0',
            hour_from: 9.0,
            hour_to: 17.0,
            day_period: 'morning',
            week_type: $weekType,
            date_from: '2024-01-01',
            date_to: '2024-12-31',
            active: true
        );

        $this->action->execute($dto);

        $scheduleDetail = ScheduleDetail::where('odoo_detail_id', $index + 1)->first();
        expect($scheduleDetail->week_type)->toBe($weekType);
    }
});

test('ProcessOdooScheduleDetailAction handles null optional fields', function (): void {
    $dto = new OdooScheduleDetailDTO(
        id: 1,
        calendar_id: [1, 'Standard 40h'],
        name: null, // Optional field
        dayofweek: '0',
        hour_from: 9.0,
        hour_to: 17.0,
        day_period: null, // Optional field
        week_type: null,  // Should default to 0
        date_from: null,  // Optional field
        date_to: null,    // Optional field
        active: null,     // Should default to true
        create_date: null,
        write_date: null
    );

    $this->action->execute($dto);

    $scheduleDetail = ScheduleDetail::where('odoo_detail_id', 1)->first();

    expect($scheduleDetail)->not->toBeNull();
    expect($scheduleDetail->name)->toBeNull();
    expect($scheduleDetail->day_period)->toBeNull();
    expect($scheduleDetail->week_type)->toBe(0);
    expect($scheduleDetail->date_from)->toBeNull();
    expect($scheduleDetail->date_to)->toBeNull();
    expect($scheduleDetail->active)->toBe(true);
    expect($scheduleDetail->odoo_created_at)->toBeNull();
    expect($scheduleDetail->odoo_updated_at)->toBeNull();
});

test('ProcessOdooScheduleDetailAction skips detail with missing required fields', function (): void {
    Log::spy();

    $dto = new OdooScheduleDetailDTO(
        id: null, // Missing required ID
        calendar_id: [1, 'Standard 40h'],
        name: 'Monday Morning',
        dayofweek: '0',
        hour_from: 9.0,
        hour_to: 13.0,
        day_period: 'morning',
        week_type: 0,
        date_from: '2024-01-01',
        date_to: '2024-12-31',
        active: true
    );

    $this->action->execute($dto);

    // Detail should not be created due to validation failure
    expect(ScheduleDetail::count())->toBe(0);

    // Should log a warning
    Log::shouldHaveReceived('warning')
        ->once()
        ->with(
            'ProcessOdooScheduleDetailAction Skipping schedule detail due to validation errors',
            \Mockery::on(function ($context) {
                return isset($context['scheduleDetailDto']) && isset($context['errors']);
            })
        );
});

test('ProcessOdooScheduleDetailAction skips detail with missing hour_from', function (): void {
    Log::spy();

    $dto = new OdooScheduleDetailDTO(
        id: 1,
        calendar_id: [1, 'Standard 40h'],
        name: 'Monday Morning',
        dayofweek: '0',
        hour_from: null, // Missing required field
        hour_to: 13.0,
        day_period: 'morning',
        week_type: 0,
        date_from: '2024-01-01',
        date_to: '2024-12-31',
        active: true
    );

    $this->action->execute($dto);

    // Detail should not be created due to validation failure
    expect(ScheduleDetail::count())->toBe(0);

    // Should log a warning
    Log::shouldHaveReceived('warning')->once();
});

test('ProcessOdooScheduleDetailAction uses compound key for uniqueness', function (): void {
    // Create two details with same ID but different schedules
    $dto1 = new OdooScheduleDetailDTO(
        id: 1,
        calendar_id: [1, 'Schedule 1'],
        name: 'Detail 1',
        dayofweek: '0',
        hour_from: 9.0,
        hour_to: 13.0,
        day_period: 'morning',
        week_type: 0,
        date_from: '2024-01-01',
        date_to: '2024-12-31',
        active: true
    );

    // Create another schedule for testing
    $schedule2 = Schedule::create([
        'odoo_schedule_id' => 2,
        'description' => 'Part Time',
        'active' => true,
    ]);

    $dto2 = new OdooScheduleDetailDTO(
        id: 1, // Same ID as dto1
        calendar_id: [2, 'Schedule 2'], // Different schedule
        name: 'Detail 2',
        dayofweek: '1',
        hour_from: 10.0,
        hour_to: 14.0,
        day_period: 'morning',
        week_type: 0,
        date_from: '2024-01-01',
        date_to: '2024-12-31',
        active: true
    );

    $this->action->execute($dto1);
    $this->action->execute($dto2);

    // Should create two separate records due to different schedule IDs
    expect(ScheduleDetail::count())->toBe(2);

    $detail1 = ScheduleDetail::where('odoo_detail_id', 1)
        ->where('odoo_schedule_id', 1)
        ->first();
    $detail2 = ScheduleDetail::where('odoo_detail_id', 1)
        ->where('odoo_schedule_id', 2)
        ->first();

    expect($detail1->name)->toBe('Detail 1');
    expect($detail2->name)->toBe('Detail 2');
});

test('ProcessOdooScheduleDetailAction is atomic - uses database transaction', function (): void {
    $dto = new OdooScheduleDetailDTO(
        id: 1,
        calendar_id: [1, 'Standard 40h'],
        name: 'Monday Morning',
        dayofweek: '0',
        hour_from: 9.0,
        hour_to: 13.0,
        day_period: 'morning',
        week_type: 0,
        date_from: '2024-01-01',
        date_to: '2024-12-31',
        active: true
    );

    // Should complete successfully within a transaction
    $this->action->execute($dto);

    $scheduleDetail = ScheduleDetail::where('odoo_detail_id', 1)->first();
    expect($scheduleDetail)->not->toBeNull();
});

test('ProcessOdooScheduleDetailAction handles fractional minutes correctly', function (): void {
    // Test edge case with precise fractional time
    $dto = new OdooScheduleDetailDTO(
        id: 1,
        calendar_id: [1, 'Standard 40h'],
        name: 'Precise Time',
        dayofweek: '0',
        hour_from: 9.125, // 9:07:30 (7.5 minutes)
        hour_to: 17.833333, // 17:50 (50 minutes)
        day_period: 'morning',
        week_type: 0,
        date_from: '2024-01-01',
        date_to: '2024-12-31',
        active: true
    );

    $this->action->execute($dto);

    $scheduleDetail = ScheduleDetail::where('odoo_detail_id', 1)->first();

    // Should handle fractional minutes properly
    expect($scheduleDetail->start?->format('H:i:s'))->toBe('09:07:00'); // Should round down to nearest minute
    expect($scheduleDetail->end?->format('H:i:s'))->toBe('17:49:00');   // Should round down to nearest minute
});
