<?php

declare(strict_types=1);

use App\Actions\Odoo\CheckOdooHealthAction;
use App\Clients\OdooApiClient;
use App\DataTransferObjects\Odoo\OdooScheduleDTO;
use App\Jobs\Sync\Odoo\SyncOdooSchedulesJob;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var \App\Clients\OdooApiClient&\Mockery\MockInterface */
    $this->odooClient = Mockery::namedMock('MockOdooApiClient', OdooApiClient::class);
    $this->job = new SyncOdooSchedulesJob($this->odooClient);
});

test('SyncOdooSchedulesJob can be constructed with OdooApiClient', function (): void {
    expect($this->job)->toBeInstanceOf(SyncOdooSchedulesJob::class);
    expect($this->job->priority)->toBe(2);
});

test('SyncOdooSchedulesJob handle method fetches and processes schedules', function (): void {
    // Mock schedules data
    $schedulesData = collect([
        new OdooScheduleDTO(
            id: 1,
            name: 'Standard 40h',
            active: true,
            attendance_ids: [1, 2, 3, 4, 5],
            hours_per_day: 8.0,
            two_weeks_calendar: false,
            two_weeks_explanation: null,
            flexible_hours: false,
            create_date: '2024-01-01 10:00:00',
            write_date: '2024-01-15 14:30:00'
        ),
        new OdooScheduleDTO(
            id: 2,
            name: 'Part Time 20h',
            active: true,
            attendance_ids: [6, 7, 8],
            hours_per_day: 4.0,
            two_weeks_calendar: true,
            two_weeks_explanation: 'Week 1: Full time, Week 2: Part time',
            flexible_hours: true,
            create_date: '2024-01-02 11:00:00',
            write_date: '2024-01-16 15:30:00'
        ),
        new OdooScheduleDTO(
            id: 3,
            name: 'Flexible Schedule',
            active: false,
            attendance_ids: [],
            hours_per_day: null,
            two_weeks_calendar: false,
            two_weeks_explanation: null,
            flexible_hours: true,
            create_date: '2024-01-03 12:00:00',
            write_date: '2024-01-17 16:30:00'
        ),
    ]);

    // Mock the API client to return our test data
    $this->odooClient
        ->shouldReceive('getSchedules')
        ->once()
        ->andReturn($schedulesData);

    // Execute the job
    $this->job->handle();

    // Verify that schedules were created in the database
    expect(Schedule::count())->toBe(3);

    $standard = Schedule::where('odoo_schedule_id', 1)->first();
    expect($standard->description)->toBe('Standard 40h');
    expect($standard->average_hours_day)->toBe(8.0);
    expect($standard->active)->toBe(true);
    expect($standard->two_weeks_calendar)->toBe(false);
    expect($standard->flexible_hours)->toBe(false);
    expect($standard->odoo_created_at->format('Y-m-d H:i:s'))->toBe('2024-01-01 10:00:00');
    expect($standard->odoo_updated_at->format('Y-m-d H:i:s'))->toBe('2024-01-15 14:30:00');

    $partTime = Schedule::where('odoo_schedule_id', 2)->first();
    expect($partTime->description)->toBe('Part Time 20h');
    expect($partTime->average_hours_day)->toBe(4.0);
    expect($partTime->two_weeks_calendar)->toBe(true);
    expect($partTime->two_weeks_explanation)->toBe('Week 1: Full time, Week 2: Part time');
    expect($partTime->flexible_hours)->toBe(true);

    $flexible = Schedule::where('odoo_schedule_id', 3)->first();
    expect($flexible->description)->toBe('Flexible Schedule');
    expect($flexible->average_hours_day)->toBeNull();
    expect($flexible->active)->toBe(false);
    expect($flexible->flexible_hours)->toBe(true);
});

test('SyncOdooSchedulesJob handle method works with empty schedules collection', function (): void {
    // Mock empty collection
    $this->odooClient
        ->shouldReceive('getSchedules')
        ->once()
        ->andReturn(collect([]));

    // Execute the job
    $this->job->handle();

    // Verify no schedules were created
    expect(Schedule::count())->toBe(0);
});

test('SyncOdooSchedulesJob handle method processes single schedule', function (): void {
    // Mock single schedule
    $schedulesData = collect([
        new OdooScheduleDTO(
            id: 1,
            name: 'Standard 40h',
            active: true,
            attendance_ids: [1, 2, 3, 4, 5],
            hours_per_day: 8.0,
            two_weeks_calendar: false,
            two_weeks_explanation: null,
            flexible_hours: false,
            create_date: '2024-01-01 10:00:00',
            write_date: '2024-01-15 14:30:00'
        ),
    ]);

    $this->odooClient
        ->shouldReceive('getSchedules')
        ->once()
        ->andReturn($schedulesData);

    // Execute the job
    $this->job->handle();

    // Verify schedule was created
    expect(Schedule::count())->toBe(1);

    $schedule = Schedule::where('odoo_schedule_id', 1)->first();
    expect($schedule->description)->toBe('Standard 40h');
    expect($schedule->average_hours_day)->toBe(8.0);
    expect($schedule->active)->toBe(true);
});

test('SyncOdooSchedulesJob handle method updates existing schedules', function (): void {
    // Create existing schedule
    Schedule::create([
        'odoo_schedule_id' => 1,
        'description' => 'Old Name',
        'average_hours_day' => 6.0,
        'two_weeks_calendar' => true,
        'flexible_hours' => false,
        'active' => false,
        'odoo_created_at' => '2023-01-01 10:00:00',
        'odoo_updated_at' => '2023-01-01 10:00:00',
    ]);

    // Mock updated schedule data
    $schedulesData = collect([
        new OdooScheduleDTO(
            id: 1,
            name: 'Updated Standard 40h',
            active: true,
            attendance_ids: [1, 2, 3, 4, 5],
            hours_per_day: 8.0,
            two_weeks_calendar: false,
            two_weeks_explanation: 'Updated explanation',
            flexible_hours: true,
            create_date: '2024-01-01 10:00:00',
            write_date: '2024-01-15 14:30:00'
        ),
    ]);

    $this->odooClient
        ->shouldReceive('getSchedules')
        ->once()
        ->andReturn($schedulesData);

    // Execute the job
    $this->job->handle();

    // Verify schedule was updated, not duplicated
    expect(Schedule::count())->toBe(1);

    $schedule = Schedule::where('odoo_schedule_id', 1)->first();
    expect($schedule->description)->toBe('Updated Standard 40h');
    expect($schedule->average_hours_day)->toBe(8.0);
    expect($schedule->active)->toBe(true);
    expect($schedule->two_weeks_calendar)->toBe(false);
    expect($schedule->two_weeks_explanation)->toBe('Updated explanation');
    expect($schedule->flexible_hours)->toBe(true);
});

test('SyncOdooSchedulesJob failed method triggers health check', function (): void {
    // Mock the CheckOdooHealthAction
    $healthAction = Mockery::mock(CheckOdooHealthAction::class);
    $healthAction->shouldReceive('__invoke')
        ->once()
        ->with($this->odooClient);

    // Bind the mock to the container
    $this->app->instance(CheckOdooHealthAction::class, $healthAction);

    // Call the failed method
    $this->job->failed();
});

test('SyncOdooSchedulesJob can be dispatched to queue', function (): void {
    Queue::fake();

    // Dispatch the job
    SyncOdooSchedulesJob::dispatch($this->odooClient);

    // Assert job was pushed to queue
    Queue::assertPushed(SyncOdooSchedulesJob::class);
});

test('SyncOdooSchedulesJob handles API exceptions gracefully', function (): void {
    // Mock API client to throw exception
    $this->odooClient
        ->shouldReceive('getSchedules')
        ->once()
        ->andThrow(new \App\Exceptions\ApiConnectionException('Connection failed'));

    // Execute the job and expect exception
    expect(fn () => $this->job->handle())
        ->toThrow(\App\Exceptions\ApiConnectionException::class, 'Connection failed');

    // Verify no schedules were created
    expect(Schedule::count())->toBe(0);
});

test('SyncOdooSchedulesJob skips invalid schedule data', function (): void {
    // Mock schedules with one invalid entry (missing required fields)
    $schedulesData = collect([
        new OdooScheduleDTO(
            id: 1,
            name: 'Valid Schedule',
            active: true,
            attendance_ids: [1, 2, 3],
            hours_per_day: 8.0,
            two_weeks_calendar: false,
            two_weeks_explanation: null,
            flexible_hours: false,
            create_date: '2024-01-01 10:00:00',
            write_date: '2024-01-15 14:30:00'
        ),
        new OdooScheduleDTO(
            id: null, // Invalid - missing ID
            name: 'Invalid Schedule',
            active: true,
            attendance_ids: [4, 5, 6],
            hours_per_day: 6.0,
            two_weeks_calendar: false,
            two_weeks_explanation: null,
            flexible_hours: false,
            create_date: '2024-01-02 11:00:00',
            write_date: '2024-01-16 15:30:00'
        ),
        new OdooScheduleDTO(
            id: 3,
            name: 'Another Valid Schedule',
            active: false,
            attendance_ids: [],
            hours_per_day: null,
            two_weeks_calendar: true,
            two_weeks_explanation: 'Bi-weekly',
            flexible_hours: true,
            create_date: '2024-01-03 12:00:00',
            write_date: '2024-01-17 16:30:00'
        ),
    ]);

    $this->odooClient
        ->shouldReceive('getSchedules')
        ->once()
        ->andReturn($schedulesData);

    // Execute the job
    $this->job->handle();

    // Verify only valid schedules were created (invalid one skipped)
    expect(Schedule::count())->toBe(2);

    expect(Schedule::where('odoo_schedule_id', 1)->exists())->toBe(true);
    expect(Schedule::where('odoo_schedule_id', 3)->exists())->toBe(true);
    expect(Schedule::where('description', 'Invalid Schedule')->exists())->toBe(false);
});

test('SyncOdooSchedulesJob handles different schedule configurations', function (): void {
    // Mock schedules with various configurations
    $schedulesData = collect([
        new OdooScheduleDTO(
            id: 1,
            name: 'Standard Full Time',
            active: true,
            attendance_ids: [1, 2, 3, 4, 5],
            hours_per_day: 8.0,
            two_weeks_calendar: false,
            two_weeks_explanation: null,
            flexible_hours: false,
            create_date: '2024-01-01 10:00:00',
            write_date: '2024-01-15 14:30:00'
        ),
        new OdooScheduleDTO(
            id: 2,
            name: 'Bi-weekly Schedule',
            active: true,
            attendance_ids: [6, 7, 8, 9, 10],
            hours_per_day: 7.5,
            two_weeks_calendar: true,
            two_weeks_explanation: 'Week 1: 40h, Week 2: 35h',
            flexible_hours: false,
            create_date: '2024-01-02 11:00:00',
            write_date: '2024-01-16 15:30:00'
        ),
        new OdooScheduleDTO(
            id: 3,
            name: 'Flexible Hours',
            active: true,
            attendance_ids: [],
            hours_per_day: null,
            two_weeks_calendar: false,
            two_weeks_explanation: null,
            flexible_hours: true,
            create_date: '2024-01-03 12:00:00',
            write_date: '2024-01-17 16:30:00'
        ),
    ]);

    $this->odooClient
        ->shouldReceive('getSchedules')
        ->once()
        ->andReturn($schedulesData);

    // Execute the job
    $this->job->handle();

    // Verify all schedules were created with correct configurations
    expect(Schedule::count())->toBe(3);

    $standard = Schedule::where('odoo_schedule_id', 1)->first();
    expect($standard->two_weeks_calendar)->toBe(false);
    expect($standard->flexible_hours)->toBe(false);
    expect($standard->average_hours_day)->toBe(8.0);

    $biweekly = Schedule::where('odoo_schedule_id', 2)->first();
    expect($biweekly->two_weeks_calendar)->toBe(true);
    expect($biweekly->two_weeks_explanation)->toBe('Week 1: 40h, Week 2: 35h');
    expect($biweekly->flexible_hours)->toBe(false);
    expect($biweekly->average_hours_day)->toBe(7.5);

    $flexible = Schedule::where('odoo_schedule_id', 3)->first();
    expect($flexible->flexible_hours)->toBe(true);
    expect($flexible->two_weeks_calendar)->toBe(false);
    expect($flexible->average_hours_day)->toBeNull();
});

test('SyncOdooSchedulesJob processes large number of schedules efficiently', function (): void {
    // Create a large collection of schedules
    $schedulesData = collect();
    for ($i = 1; $i <= 30; $i++) {
        $schedulesData->push(new OdooScheduleDTO(
            id: $i,
            name: "Schedule {$i}",
            active: ($i % 5 !== 0), // Most active, some inactive
            attendance_ids: range(($i - 1) * 5 + 1, $i * 5),
            hours_per_day: 6.0 + ($i % 3), // 6.0, 7.0, or 8.0
            two_weeks_calendar: ($i % 3 === 0),
            two_weeks_explanation: ($i % 3 === 0) ? "Bi-weekly {$i}" : null,
            flexible_hours: ($i % 4 === 0),
            create_date: '2024-01-01 10:00:00',
            write_date: '2024-01-15 14:30:00'
        ));
    }

    $this->odooClient
        ->shouldReceive('getSchedules')
        ->once()
        ->andReturn($schedulesData);

    // Execute the job
    $this->job->handle();

    // Verify all schedules were processed
    expect(Schedule::count())->toBe(30);

    // Verify some random samples
    $schedule15 = Schedule::where('odoo_schedule_id', 15)->first();
    expect($schedule15->description)->toBe('Schedule 15');
    expect($schedule15->active)->toBe(false); // 15 % 5 === 0
    expect($schedule15->two_weeks_calendar)->toBe(true); // 15 % 3 === 0

    $schedule16 = Schedule::where('odoo_schedule_id', 16)->first();
    expect($schedule16->flexible_hours)->toBe(true); // 16 % 4 === 0
    expect($schedule16->average_hours_day)->toBe(7.0); // 6.0 + (16 % 3) = 6.0 + 1
});

test('SyncOdooSchedulesJob maintains data integrity during partial failures', function (): void {
    // Create some existing schedules
    Schedule::create(['odoo_schedule_id' => 1, 'description' => 'Existing 1', 'active' => true]);
    Schedule::create(['odoo_schedule_id' => 2, 'description' => 'Existing 2', 'active' => false]);

    // Mock schedules with mix of valid and invalid data
    $schedulesData = collect([
        new OdooScheduleDTO(
            id: 1,
            name: 'Updated Existing 1',
            active: false,
            attendance_ids: [1, 2, 3],
            hours_per_day: 8.0,
            two_weeks_calendar: false,
            two_weeks_explanation: null,
            flexible_hours: true,
            create_date: '2024-01-01 10:00:00',
            write_date: '2024-01-15 14:30:00'
        ), // Update existing
        new OdooScheduleDTO(
            id: null, // Invalid - missing ID
            name: 'Invalid New',
            active: true,
            attendance_ids: [4, 5, 6],
            hours_per_day: 6.0,
            two_weeks_calendar: false,
            two_weeks_explanation: null,
            flexible_hours: false,
            create_date: '2024-01-02 11:00:00',
            write_date: '2024-01-16 15:30:00'
        ), // Invalid new
        new OdooScheduleDTO(
            id: 4,
            name: 'Valid New Schedule',
            active: true,
            attendance_ids: [7, 8, 9],
            hours_per_day: 7.5,
            two_weeks_calendar: true,
            two_weeks_explanation: 'New bi-weekly',
            flexible_hours: false,
            create_date: '2024-01-04 13:00:00',
            write_date: '2024-01-18 17:30:00'
        ), // Valid new
    ]);

    $this->odooClient
        ->shouldReceive('getSchedules')
        ->once()
        ->andReturn($schedulesData);

    // Execute the job
    $this->job->handle();

    // Verify data integrity
    expect(Schedule::count())->toBe(3); // 2 existing + 1 new valid

    // Existing schedule should be updated
    $updated = Schedule::where('odoo_schedule_id', 1)->first();
    expect($updated->description)->toBe('Updated Existing 1');
    expect($updated->active)->toBe(false);
    expect($updated->average_hours_day)->toBe(8.0);
    expect($updated->flexible_hours)->toBe(true);

    // Second existing schedule should remain unchanged
    $unchanged = Schedule::where('odoo_schedule_id', 2)->first();
    expect($unchanged->description)->toBe('Existing 2');
    expect($unchanged->active)->toBe(false);

    // New valid schedule should be created
    $newSchedule = Schedule::where('odoo_schedule_id', 4)->first();
    expect($newSchedule->description)->toBe('Valid New Schedule');
    expect($newSchedule->active)->toBe(true);
    expect($newSchedule->average_hours_day)->toBe(7.5);
    expect($newSchedule->two_weeks_calendar)->toBe(true);
    expect($newSchedule->two_weeks_explanation)->toBe('New bi-weekly');

    // Invalid schedule should not exist
    expect(Schedule::where('description', 'Invalid New')->exists())->toBe(false);
});

test('SyncOdooSchedulesJob extends BaseSyncJob', function (): void {
    expect($this->job)->toBeInstanceOf(\App\Jobs\Sync\BaseSyncJob::class);
});

test('SyncOdooSchedulesJob has correct priority', function (): void {
    expect($this->job->priority)->toBe(2);
});

afterEach(function (): void {
    Mockery::close();
});
