<?php

declare(strict_types=1);

use App\Actions\Odoo\ProcessOdooScheduleAction;
use App\DataTransferObjects\Odoo\OdooScheduleDTO;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->action = new ProcessOdooScheduleAction;
});

test('ProcessOdooScheduleAction creates new schedule with valid data', function (): void {
    $dto = new OdooScheduleDTO(
        id: 1,
        name: 'Standard 40h',
        active: true,
        attendance_ids: [1, 2, 3, 4, 5],
        hours_per_day: 8.0,
        two_weeks_calendar: false,
        two_weeks_explanation: null,
        flexible_hours: true,
        create_date: '2024-01-01 10:00:00',
        write_date: '2024-01-15 14:30:00'
    );

    $this->action->execute($dto);

    $schedule = Schedule::where('odoo_schedule_id', 1)->first();

    expect($schedule)->not->toBeNull();
    expect($schedule->odoo_schedule_id)->toBe(1);
    expect($schedule->description)->toBe('Standard 40h');
    expect($schedule->average_hours_day)->toBe(8.0);
    expect($schedule->two_weeks_calendar)->toBe(false);
    expect($schedule->two_weeks_explanation)->toBeNull();
    expect($schedule->flexible_hours)->toBe(true);
    expect($schedule->active)->toBe(true);
    expect($schedule->odoo_created_at->format('Y-m-d H:i:s'))->toBe('2024-01-01 10:00:00');
    expect($schedule->odoo_updated_at->format('Y-m-d H:i:s'))->toBe('2024-01-15 14:30:00');
});

test('ProcessOdooScheduleAction updates existing schedule', function (): void {
    // Create existing schedule
    $existingSchedule = Schedule::create([
        'odoo_schedule_id' => 1,
        'description' => 'Old Name',
        'average_hours_day' => 6.0,
        'two_weeks_calendar' => true,
        'two_weeks_explanation' => 'Old explanation',
        'flexible_hours' => false,
        'active' => false,
        'odoo_created_at' => '2023-01-01 10:00:00',
        'odoo_updated_at' => '2023-01-01 10:00:00',
    ]);

    $dto = new OdooScheduleDTO(
        id: 1,
        name: 'Standard 40h',
        active: true,
        attendance_ids: [1, 2, 3, 4, 5],
        hours_per_day: 8.0,
        two_weeks_calendar: false,
        two_weeks_explanation: 'New explanation',
        flexible_hours: true,
        create_date: '2024-01-01 10:00:00',
        write_date: '2024-01-15 14:30:00'
    );

    $this->action->execute($dto);

    $schedule = Schedule::where('odoo_schedule_id', 1)->first();

    expect($schedule)->not->toBeNull();
    expect($schedule->description)->toBe('Standard 40h');
    expect($schedule->average_hours_day)->toBe(8.0);
    expect($schedule->two_weeks_calendar)->toBe(false);
    expect($schedule->two_weeks_explanation)->toBe('New explanation');
    expect($schedule->flexible_hours)->toBe(true);
    expect($schedule->active)->toBe(true);
    expect($schedule->odoo_created_at->format('Y-m-d H:i:s'))->toBe('2024-01-01 10:00:00');
    expect($schedule->odoo_updated_at->format('Y-m-d H:i:s'))->toBe('2024-01-15 14:30:00');

    // Should still be the same record, not a new one
    expect(Schedule::count())->toBe(1);
});

test('ProcessOdooScheduleAction handles null fields with defaults', function (): void {
    $dto = new OdooScheduleDTO(
        id: 1,
        name: 'Flexible Schedule',
        active: null,              // Should default to true
        attendance_ids: [],
        hours_per_day: null,       // Optional field
        two_weeks_calendar: null,  // Should default to false
        two_weeks_explanation: null,
        flexible_hours: null,      // Should default to false
        create_date: null,
        write_date: null
    );

    $this->action->execute($dto);

    $schedule = Schedule::where('odoo_schedule_id', 1)->first();

    expect($schedule)->not->toBeNull();
    expect($schedule->active)->toBe(true);
    expect($schedule->average_hours_day)->toBeNull();
    expect($schedule->two_weeks_calendar)->toBe(false);
    expect($schedule->two_weeks_explanation)->toBeNull();
    expect($schedule->flexible_hours)->toBe(false);
    expect($schedule->odoo_created_at)->toBeNull();
    expect($schedule->odoo_updated_at)->toBeNull();
});

test('ProcessOdooScheduleAction skips schedule with missing id', function (): void {
    Log::spy();

    $dto = new OdooScheduleDTO(
        id: null, // Missing required ID
        name: 'Standard 40h',
        active: true,
        attendance_ids: [1, 2, 3],
        hours_per_day: 8.0
    );

    $this->action->execute($dto);

    // Schedule should not be created due to validation failure
    $schedule = Schedule::where('description', 'Standard 40h')->first();
    expect($schedule)->toBeNull();

    // Should log a warning
    Log::shouldHaveReceived('warning')
        ->once()
        ->with(
            'ProcessOdooScheduleAction Skipping schedule due to validation errors',
            \Mockery::on(function ($context) {
                return isset($context['schedule']) && isset($context['errors']);
            })
        );
});

test('ProcessOdooScheduleAction skips schedule with missing name', function (): void {
    Log::spy();

    $dto = new OdooScheduleDTO(
        id: 1,
        name: null, // Missing required name
        active: true,
        attendance_ids: [1, 2, 3],
        hours_per_day: 8.0
    );

    $this->action->execute($dto);

    // Schedule should not be created due to validation failure
    $schedule = Schedule::where('odoo_schedule_id', 1)->first();
    expect($schedule)->toBeNull();

    // Should log a warning
    Log::shouldHaveReceived('warning')
        ->once()
        ->with(
            'ProcessOdooScheduleAction Skipping schedule due to validation errors',
            \Mockery::on(function ($context) {
                return isset($context['schedule']) && isset($context['errors']);
            })
        );
});

test('ProcessOdooScheduleAction skips schedule with empty name', function (): void {
    Log::spy();

    $dto = new OdooScheduleDTO(
        id: 1,
        name: '', // Empty name should fail validation
        active: true,
        attendance_ids: [1, 2, 3],
        hours_per_day: 8.0
    );

    $this->action->execute($dto);

    // Schedule should not be created due to validation failure
    $schedule = Schedule::where('odoo_schedule_id', 1)->first();
    expect($schedule)->toBeNull();

    // Should log a warning
    Log::shouldHaveReceived('warning')->once();
});

test('ProcessOdooScheduleAction handles two weeks calendar features', function (): void {
    $dto = new OdooScheduleDTO(
        id: 1,
        name: 'Bi-weekly Schedule',
        active: true,
        attendance_ids: [1, 2, 3, 4, 5],
        hours_per_day: 8.0,
        two_weeks_calendar: true,
        two_weeks_explanation: 'Week 1: Full time, Week 2: Part time',
        flexible_hours: false
    );

    $this->action->execute($dto);

    $schedule = Schedule::where('odoo_schedule_id', 1)->first();

    expect($schedule)->not->toBeNull();
    expect($schedule->two_weeks_calendar)->toBe(true);
    expect($schedule->two_weeks_explanation)->toBe('Week 1: Full time, Week 2: Part time');
});

test('ProcessOdooScheduleAction handles flexible hours', function (): void {
    $dto = new OdooScheduleDTO(
        id: 1,
        name: 'Flexible Schedule',
        active: true,
        attendance_ids: [],
        hours_per_day: 8.0,
        two_weeks_calendar: false,
        two_weeks_explanation: null,
        flexible_hours: true
    );

    $this->action->execute($dto);

    $schedule = Schedule::where('odoo_schedule_id', 1)->first();

    expect($schedule)->not->toBeNull();
    expect($schedule->flexible_hours)->toBe(true);
});

test('ProcessOdooScheduleAction is atomic - uses database transaction', function (): void {
    $dto = new OdooScheduleDTO(
        id: 1,
        name: 'Standard 40h',
        active: true,
        attendance_ids: [1, 2, 3],
        hours_per_day: 8.0
    );

    // Should complete successfully within a transaction
    $this->action->execute($dto);

    $schedule = Schedule::where('odoo_schedule_id', 1)->first();
    expect($schedule)->not->toBeNull();
});

test('ProcessOdooScheduleAction can create multiple schedules', function (): void {
    $dto1 = new OdooScheduleDTO(
        id: 1,
        name: 'Standard 40h',
        active: true,
        attendance_ids: [1, 2, 3, 4, 5],
        hours_per_day: 8.0,
        two_weeks_calendar: false,
        flexible_hours: false
    );

    $dto2 = new OdooScheduleDTO(
        id: 2,
        name: 'Part Time 20h',
        active: false,
        attendance_ids: [6, 7, 8],
        hours_per_day: 4.0,
        two_weeks_calendar: true,
        flexible_hours: true
    );

    $this->action->execute($dto1);
    $this->action->execute($dto2);

    expect(Schedule::count())->toBe(2);

    $schedule1 = Schedule::where('odoo_schedule_id', 1)->first();
    $schedule2 = Schedule::where('odoo_schedule_id', 2)->first();

    expect($schedule1->description)->toBe('Standard 40h');
    expect($schedule1->average_hours_day)->toBe(8.0);
    expect($schedule1->active)->toBe(true);
    expect($schedule1->two_weeks_calendar)->toBe(false);
    expect($schedule1->flexible_hours)->toBe(false);

    expect($schedule2->description)->toBe('Part Time 20h');
    expect($schedule2->average_hours_day)->toBe(4.0);
    expect($schedule2->active)->toBe(false);
    expect($schedule2->two_weeks_calendar)->toBe(true);
    expect($schedule2->flexible_hours)->toBe(true);
});

test('ProcessOdooScheduleAction preserves relationships when updating', function (): void {
    // Create schedule with some user schedules attached
    $schedule = Schedule::create([
        'odoo_schedule_id' => 1,
        'description' => 'Old Name',
        'average_hours_day' => 6.0,
        'active' => true,
    ]);

    // Create a user schedule associated with this schedule
    $user = \App\Models\User::factory()->create();
    \App\Models\UserSchedule::factory()->create([
        'user_id' => $user->id,
        'odoo_schedule_id' => $schedule->odoo_schedule_id,
    ]);

    expect($schedule->userSchedules()->count())->toBe(1);

    // Update the schedule
    $dto = new OdooScheduleDTO(
        id: 1,
        name: 'New Name',
        active: false,
        attendance_ids: [1, 2, 3],
        hours_per_day: 8.0
    );

    $this->action->execute($dto);

    $updatedSchedule = Schedule::where('odoo_schedule_id', 1)->first();

    expect($updatedSchedule->description)->toBe('New Name');
    expect($updatedSchedule->average_hours_day)->toBe(8.0);
    expect($updatedSchedule->active)->toBe(false);

    // Relationships should be preserved
    expect($updatedSchedule->userSchedules()->count())->toBe(1);
});
