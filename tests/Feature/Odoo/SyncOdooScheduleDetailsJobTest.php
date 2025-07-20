<?php

declare(strict_types=1);

use App\Actions\Odoo\CheckOdooHealthAction;
use App\Clients\OdooApiClient;
use App\DataTransferObjects\Odoo\OdooScheduleDetailDTO;
use App\Jobs\Sync\Odoo\SyncOdooScheduleDetailsJob;
use App\Models\Schedule;
use App\Models\ScheduleDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->odooClient = Mockery::namedMock('MockOdooApiClient', OdooApiClient::class);
    $this->job = new SyncOdooScheduleDetailsJob($this->odooClient);

    // Create test schedules
    $this->schedule1 = Schedule::create([
        'odoo_schedule_id' => 1,
        'description' => 'Standard 40h',
        'active' => true,
    ]);

    $this->schedule2 = Schedule::create([
        'odoo_schedule_id' => 2,
        'description' => 'Part Time',
        'active' => true,
    ]);
});

test('SyncOdooScheduleDetailsJob can be constructed with OdooApiClient', function (): void {
    expect($this->job)->toBeInstanceOf(SyncOdooScheduleDetailsJob::class);
    expect($this->job->priority)->toBe(2);
});

test('SyncOdooScheduleDetailsJob handle method fetches and processes schedule details', function (): void {
    // Mock schedule details data
    $scheduleDetailsData = collect([
        new OdooScheduleDetailDTO(
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
        ),
        new OdooScheduleDetailDTO(
            id: 2,
            calendar_id: [1, 'Standard 40h'],
            name: 'Monday Afternoon',
            dayofweek: '0', // Monday
            hour_from: 14.0, // 14:00
            hour_to: 18.0,   // 18:00
            day_period: 'afternoon',
            week_type: 0,
            date_from: '2024-01-01',
            date_to: '2024-12-31',
            active: true,
            create_date: '2024-01-01 10:00:00',
            write_date: '2024-01-15 14:30:00'
        ),
        new OdooScheduleDetailDTO(
            id: 3,
            calendar_id: [2, 'Part Time'],
            name: 'Tuesday Part Time',
            dayofweek: '1', // Tuesday
            hour_from: 9.5,  // 09:30
            hour_to: 13.5,   // 13:30
            day_period: 'morning',
            week_type: 1,
            date_from: '2024-01-01',
            date_to: '2024-06-30',
            active: false,
            create_date: '2024-01-02 11:00:00',
            write_date: '2024-01-16 15:30:00'
        ),
    ]);

    // Mock the API client to return our test data
    $this->odooClient
        ->shouldReceive('getScheduleDetails')
        ->once()
        ->andReturn($scheduleDetailsData);

    // Execute the job
    $this->job->handle();

    // Verify that schedule details were created in the database
    expect(ScheduleDetail::count())->toBe(3);

    $mondayMorning = ScheduleDetail::where('odoo_detail_id', 1)->first();
    expect($mondayMorning->name)->toBe('Monday Morning');
    expect($mondayMorning->odoo_schedule_id)->toBe(1);
    expect($mondayMorning->weekday)->toBe(0);
    expect($mondayMorning->start->format('H:i:s'))->toBe('09:00:00');
    expect($mondayMorning->end->format('H:i:s'))->toBe('13:00:00');
    expect($mondayMorning->day_period)->toBe('morning');
    expect($mondayMorning->week_type)->toBe(0);
    expect($mondayMorning->active)->toBe(true);

    $mondayAfternoon = ScheduleDetail::where('odoo_detail_id', 2)->first();
    expect($mondayAfternoon->name)->toBe('Monday Afternoon');
    expect($mondayAfternoon->start->format('H:i:s'))->toBe('14:00:00');
    expect($mondayAfternoon->end->format('H:i:s'))->toBe('18:00:00');
    expect($mondayAfternoon->day_period)->toBe('afternoon');

    $tuesdayPartTime = ScheduleDetail::where('odoo_detail_id', 3)->first();
    expect($tuesdayPartTime->name)->toBe('Tuesday Part Time');
    expect($tuesdayPartTime->odoo_schedule_id)->toBe(2);
    expect($tuesdayPartTime->weekday)->toBe(1);
    expect($tuesdayPartTime->start->format('H:i:s'))->toBe('09:30:00');
    expect($tuesdayPartTime->end->format('H:i:s'))->toBe('13:30:00');
    expect($tuesdayPartTime->week_type)->toBe(1);
    expect($tuesdayPartTime->active)->toBe(false);
});

test('SyncOdooScheduleDetailsJob handle method works with empty schedule details collection', function (): void {
    // Mock empty collection
    $this->odooClient
        ->shouldReceive('getScheduleDetails')
        ->once()
        ->andReturn(collect([]));

    // Execute the job
    $this->job->handle();

    // Verify no schedule details were created
    expect(ScheduleDetail::count())->toBe(0);
});

test('SyncOdooScheduleDetailsJob handle method processes single schedule detail', function (): void {
    // Mock single schedule detail
    $scheduleDetailsData = collect([
        new OdooScheduleDetailDTO(
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
            active: true,
            create_date: '2024-01-01 10:00:00',
            write_date: '2024-01-15 14:30:00'
        ),
    ]);

    $this->odooClient
        ->shouldReceive('getScheduleDetails')
        ->once()
        ->andReturn($scheduleDetailsData);

    // Execute the job
    $this->job->handle();

    // Verify schedule detail was created
    expect(ScheduleDetail::count())->toBe(1);

    $detail = ScheduleDetail::where('odoo_detail_id', 1)->first();
    expect($detail->name)->toBe('Monday Morning');
    expect($detail->odoo_schedule_id)->toBe(1);
    expect($detail->weekday)->toBe(0);
    expect($detail->start->format('H:i:s'))->toBe('09:00:00');
    expect($detail->end->format('H:i:s'))->toBe('13:00:00');
});

test('SyncOdooScheduleDetailsJob handle method updates existing schedule details', function (): void {
    // Create existing schedule detail
    ScheduleDetail::create([
        'odoo_detail_id' => 1,
        'odoo_schedule_id' => 1,
        'name' => 'Old Name',
        'weekday' => 1,
        'start' => '08:00:00',
        'end' => '12:00:00',
        'day_period' => 'morning',
        'week_type' => 1,
        'active' => false,
    ]);

    // Mock updated schedule detail data
    $scheduleDetailsData = collect([
        new OdooScheduleDetailDTO(
            id: 1,
            calendar_id: [1, 'Standard 40h'],
            name: 'Updated Monday Morning',
            dayofweek: '0',
            hour_from: 9.0,
            hour_to: 13.0,
            day_period: 'morning',
            week_type: 0,
            date_from: '2024-01-01',
            date_to: '2024-12-31',
            active: true,
            create_date: '2024-01-01 10:00:00',
            write_date: '2024-01-15 14:30:00'
        ),
    ]);

    $this->odooClient
        ->shouldReceive('getScheduleDetails')
        ->once()
        ->andReturn($scheduleDetailsData);

    // Execute the job
    $this->job->handle();

    // Verify schedule detail was updated, not duplicated
    expect(ScheduleDetail::count())->toBe(1);

    $detail = ScheduleDetail::where('odoo_detail_id', 1)->first();
    expect($detail->name)->toBe('Updated Monday Morning');
    expect($detail->weekday)->toBe(0);
    expect($detail->start->format('H:i:s'))->toBe('09:00:00');
    expect($detail->end->format('H:i:s'))->toBe('13:00:00');
    expect($detail->week_type)->toBe(0);
    expect($detail->active)->toBe(true);
});

test('SyncOdooScheduleDetailsJob failed method triggers health check', function (): void {
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

test('SyncOdooScheduleDetailsJob can be dispatched to queue', function (): void {
    Queue::fake();

    // Dispatch the job
    SyncOdooScheduleDetailsJob::dispatch($this->odooClient);

    // Assert job was pushed to queue
    Queue::assertPushed(SyncOdooScheduleDetailsJob::class);
});

test('SyncOdooScheduleDetailsJob handles API exceptions gracefully', function (): void {
    // Mock API client to throw exception
    $this->odooClient
        ->shouldReceive('getScheduleDetails')
        ->once()
        ->andThrow(new \App\Exceptions\ApiConnectionException('Connection failed'));

    // Execute the job and expect exception
    expect(fn () => $this->job->handle())
        ->toThrow(\App\Exceptions\ApiConnectionException::class, 'Connection failed');

    // Verify no schedule details were created
    expect(ScheduleDetail::count())->toBe(0);
});

test('SyncOdooScheduleDetailsJob skips invalid schedule detail data', function (): void {
    // Mock schedule details with one invalid entry (missing required fields)
    $scheduleDetailsData = collect([
        new OdooScheduleDetailDTO(
            id: 1,
            calendar_id: [1, 'Standard 40h'],
            name: 'Valid Detail',
            dayofweek: '0',
            hour_from: 9.0,
            hour_to: 13.0,
            day_period: 'morning',
            week_type: 0,
            date_from: '2024-01-01',
            date_to: '2024-12-31',
            active: true,
            create_date: '2024-01-01 10:00:00',
            write_date: '2024-01-15 14:30:00'
        ),
        new OdooScheduleDetailDTO(
            id: null, // Invalid - missing ID
            calendar_id: [1, 'Standard 40h'],
            name: 'Invalid Detail',
            dayofweek: '1',
            hour_from: 10.0,
            hour_to: 14.0,
            day_period: 'morning',
            week_type: 0,
            date_from: '2024-01-01',
            date_to: '2024-12-31',
            active: true,
            create_date: '2024-01-02 11:00:00',
            write_date: '2024-01-16 15:30:00'
        ),
        new OdooScheduleDetailDTO(
            id: 3,
            calendar_id: [2, 'Part Time'],
            name: 'Another Valid Detail',
            dayofweek: '2',
            hour_from: 8.0,
            hour_to: 12.0,
            day_period: 'morning',
            week_type: 0,
            date_from: '2024-01-01',
            date_to: '2024-12-31',
            active: false,
            create_date: '2024-01-03 12:00:00',
            write_date: '2024-01-17 16:30:00'
        ),
    ]);

    $this->odooClient
        ->shouldReceive('getScheduleDetails')
        ->once()
        ->andReturn($scheduleDetailsData);

    // Execute the job
    $this->job->handle();

    // Verify only valid schedule details were created (invalid one skipped)
    expect(ScheduleDetail::count())->toBe(2);

    expect(ScheduleDetail::where('odoo_detail_id', 1)->exists())->toBe(true);
    expect(ScheduleDetail::where('odoo_detail_id', 3)->exists())->toBe(true);
    expect(ScheduleDetail::where('name', 'Invalid Detail')->exists())->toBe(false);
});

test('SyncOdooScheduleDetailsJob handles different time formats correctly', function (): void {
    // Mock schedule details with various time formats
    $scheduleDetailsData = collect([
        new OdooScheduleDetailDTO(
            id: 1,
            calendar_id: [1, 'Standard 40h'],
            name: 'Morning Shift',
            dayofweek: '0',
            hour_from: 9.0,    // 09:00
            hour_to: 13.5,     // 13:30
            day_period: 'morning',
            week_type: 0,
            date_from: '2024-01-01',
            date_to: '2024-12-31',
            active: true,
            create_date: '2024-01-01 10:00:00',
            write_date: '2024-01-15 14:30:00'
        ),
        new OdooScheduleDetailDTO(
            id: 2,
            calendar_id: [1, 'Standard 40h'],
            name: 'Afternoon Shift',
            dayofweek: '0',
            hour_from: 14.25,  // 14:15
            hour_to: 18.75,    // 18:45
            day_period: 'afternoon',
            week_type: 0,
            date_from: '2024-01-01',
            date_to: '2024-12-31',
            active: true,
            create_date: '2024-01-01 10:00:00',
            write_date: '2024-01-15 14:30:00'
        ),
        new OdooScheduleDetailDTO(
            id: 3,
            calendar_id: [1, 'Standard 40h'],
            name: 'Night Shift',
            dayofweek: '0',
            hour_from: 22.0,   // 22:00
            hour_to: 6.0,      // 06:00 (next day)
            day_period: 'morning',
            week_type: 0,
            date_from: '2024-01-01',
            date_to: '2024-12-31',
            active: true,
            create_date: '2024-01-01 10:00:00',
            write_date: '2024-01-15 14:30:00'
        ),
    ]);

    $this->odooClient
        ->shouldReceive('getScheduleDetails')
        ->once()
        ->andReturn($scheduleDetailsData);

    // Execute the job
    $this->job->handle();

    // Verify all schedule details were created with correct time formatting
    expect(ScheduleDetail::count())->toBe(3);

    $morning = ScheduleDetail::where('odoo_detail_id', 1)->first();
    expect($morning->start->format('H:i:s'))->toBe('09:00:00');
    expect($morning->end->format('H:i:s'))->toBe('13:30:00');

    $afternoon = ScheduleDetail::where('odoo_detail_id', 2)->first();
    expect($afternoon->start->format('H:i:s'))->toBe('14:15:00');
    expect($afternoon->end->format('H:i:s'))->toBe('18:45:00');

    $night = ScheduleDetail::where('odoo_detail_id', 3)->first();
    expect($night->start->format('H:i:s'))->toBe('22:00:00');
    expect($night->end->format('H:i:s'))->toBe('06:00:00');
});

test('SyncOdooScheduleDetailsJob handles different weekdays and week types', function (): void {
    // Mock schedule details for different weekdays and week types
    $scheduleDetailsData = collect([
        new OdooScheduleDetailDTO(
            id: 1,
            calendar_id: [1, 'Standard 40h'],
            name: 'Monday Week 1',
            dayofweek: '0', // Monday
            hour_from: 9.0,
            hour_to: 17.0,
            day_period: 'morning',
            week_type: 1, // Week 1 only
            date_from: '2024-01-01',
            date_to: '2024-12-31',
            active: true,
            create_date: '2024-01-01 10:00:00',
            write_date: '2024-01-15 14:30:00'
        ),
        new OdooScheduleDetailDTO(
            id: 2,
            calendar_id: [1, 'Standard 40h'],
            name: 'Friday Week 2',
            dayofweek: '4', // Friday
            hour_from: 9.0,
            hour_to: 13.0,
            day_period: 'morning',
            week_type: 2, // Week 2 only
            date_from: '2024-01-01',
            date_to: '2024-12-31',
            active: true,
            create_date: '2024-01-01 10:00:00',
            write_date: '2024-01-15 14:30:00'
        ),
        new OdooScheduleDetailDTO(
            id: 3,
            calendar_id: [1, 'Standard 40h'],
            name: 'Sunday Both Weeks',
            dayofweek: '6', // Sunday
            hour_from: 10.0,
            hour_to: 14.0,
            day_period: 'morning',
            week_type: 0, // Both weeks
            date_from: '2024-01-01',
            date_to: '2024-12-31',
            active: true,
            create_date: '2024-01-01 10:00:00',
            write_date: '2024-01-15 14:30:00'
        ),
    ]);

    $this->odooClient
        ->shouldReceive('getScheduleDetails')
        ->once()
        ->andReturn($scheduleDetailsData);

    // Execute the job
    $this->job->handle();

    // Verify all schedule details were created with correct weekdays and week types
    expect(ScheduleDetail::count())->toBe(3);

    $mondayWeek1 = ScheduleDetail::where('odoo_detail_id', 1)->first();
    expect($mondayWeek1->weekday)->toBe(0);
    expect($mondayWeek1->week_type)->toBe(1);

    $fridayWeek2 = ScheduleDetail::where('odoo_detail_id', 2)->first();
    expect($fridayWeek2->weekday)->toBe(4);
    expect($fridayWeek2->week_type)->toBe(2);

    $sundayBoth = ScheduleDetail::where('odoo_detail_id', 3)->first();
    expect($sundayBoth->weekday)->toBe(6);
    expect($sundayBoth->week_type)->toBe(0);
});

test('SyncOdooScheduleDetailsJob handles compound keys correctly', function (): void {
    // Create schedule details with same detail ID but different schedules (should be allowed)
    $scheduleDetailsData = collect([
        new OdooScheduleDetailDTO(
            id: 1,
            calendar_id: [1, 'Standard 40h'],
            name: 'Detail for Schedule 1',
            dayofweek: '0',
            hour_from: 9.0,
            hour_to: 13.0,
            day_period: 'morning',
            week_type: 0,
            date_from: '2024-01-01',
            date_to: '2024-12-31',
            active: true,
            create_date: '2024-01-01 10:00:00',
            write_date: '2024-01-15 14:30:00'
        ),
        new OdooScheduleDetailDTO(
            id: 1, // Same detail ID
            calendar_id: [2, 'Part Time'], // Different schedule
            name: 'Detail for Schedule 2',
            dayofweek: '1',
            hour_from: 10.0,
            hour_to: 14.0,
            day_period: 'morning',
            week_type: 0,
            date_from: '2024-01-01',
            date_to: '2024-12-31',
            active: true,
            create_date: '2024-01-01 10:00:00',
            write_date: '2024-01-15 14:30:00'
        ),
    ]);

    $this->odooClient
        ->shouldReceive('getScheduleDetails')
        ->once()
        ->andReturn($scheduleDetailsData);

    // Execute the job
    $this->job->handle();

    // Verify both details were created (compound key allows same detail ID for different schedules)
    expect(ScheduleDetail::count())->toBe(2);

    $detail1 = ScheduleDetail::where('odoo_detail_id', 1)
        ->where('odoo_schedule_id', 1)
        ->first();
    expect($detail1->name)->toBe('Detail for Schedule 1');
    expect($detail1->weekday)->toBe(0);

    $detail2 = ScheduleDetail::where('odoo_detail_id', 1)
        ->where('odoo_schedule_id', 2)
        ->first();
    expect($detail2->name)->toBe('Detail for Schedule 2');
    expect($detail2->weekday)->toBe(1);
});

test('SyncOdooScheduleDetailsJob processes large number of schedule details efficiently', function (): void {
    // Create a large collection of schedule details
    $scheduleDetailsData = collect();
    for ($i = 1; $i <= 50; $i++) {
        $scheduleDetailsData->push(new OdooScheduleDetailDTO(
            id: $i,
            calendar_id: [($i % 2) + 1, 'Schedule '.(($i % 2) + 1)], // Alternate between schedules 1 and 2
            name: "Detail {$i}",
            dayofweek: (string) ($i % 7), // Cycle through weekdays 0-6
            hour_from: 8.0 + ($i % 4), // 8.0, 9.0, 10.0, 11.0
            hour_to: 12.0 + ($i % 4), // 12.0, 13.0, 14.0, 15.0
            day_period: ($i % 2 === 0) ? 'morning' : 'afternoon',
            week_type: $i % 3, // 0, 1, 2
            date_from: '2024-01-01',
            date_to: '2024-12-31',
            active: ($i % 5 !== 0), // Most active, some inactive
            create_date: '2024-01-01 10:00:00',
            write_date: '2024-01-15 14:30:00'
        ));
    }

    $this->odooClient
        ->shouldReceive('getScheduleDetails')
        ->once()
        ->andReturn($scheduleDetailsData);

    // Execute the job
    $this->job->handle();

    // Verify all schedule details were processed
    expect(ScheduleDetail::count())->toBe(50);

    // Verify some random samples
    $detail25 = ScheduleDetail::where('odoo_detail_id', 25)->first();
    expect($detail25->name)->toBe('Detail 25');
    expect($detail25->weekday)->toBe(4); // 25 % 7 = 4
    expect($detail25->start->format('H:i:s'))->toBe('09:00:00'); // 8.0 + (25 % 4) = 8.0 + 1
    expect($detail25->active)->toBe(false); // 25 % 5 === 0

    $detail26 = ScheduleDetail::where('odoo_detail_id', 26)->first();
    expect($detail26->day_period)->toBe('morning'); // 26 % 2 === 0
    expect($detail26->week_type)->toBe(2); // 26 % 3 = 2
});

test('SyncOdooScheduleDetailsJob maintains data integrity during partial failures', function (): void {
    // Create some existing schedule details
    ScheduleDetail::create([
        'odoo_detail_id' => 1,
        'odoo_schedule_id' => 1,
        'name' => 'Existing 1',
        'weekday' => 1,
        'start' => '08:00:00',
        'end' => '12:00:00',
        'active' => true,
    ]);
    ScheduleDetail::create([
        'odoo_detail_id' => 2,
        'odoo_schedule_id' => 1,
        'name' => 'Existing 2',
        'weekday' => 2,
        'start' => '09:00:00',
        'end' => '13:00:00',
        'active' => false,
    ]);

    // Mock schedule details with mix of valid and invalid data
    $scheduleDetailsData = collect([
        new OdooScheduleDetailDTO(
            id: 1,
            calendar_id: [1, 'Standard 40h'],
            name: 'Updated Existing 1',
            dayofweek: '0',
            hour_from: 9.0,
            hour_to: 13.0,
            day_period: 'morning',
            week_type: 0,
            date_from: '2024-01-01',
            date_to: '2024-12-31',
            active: false,
            create_date: '2024-01-01 10:00:00',
            write_date: '2024-01-15 14:30:00'
        ), // Update existing
        new OdooScheduleDetailDTO(
            id: null, // Invalid - missing ID
            calendar_id: [1, 'Standard 40h'],
            name: 'Invalid New',
            dayofweek: '3',
            hour_from: 10.0,
            hour_to: 14.0,
            day_period: 'morning',
            week_type: 0,
            date_from: '2024-01-01',
            date_to: '2024-12-31',
            active: true,
            create_date: '2024-01-02 11:00:00',
            write_date: '2024-01-16 15:30:00'
        ), // Invalid new
        new OdooScheduleDetailDTO(
            id: 4,
            calendar_id: [2, 'Part Time'],
            name: 'Valid New Detail',
            dayofweek: '4',
            hour_from: 11.0,
            hour_to: 15.0,
            day_period: 'morning',
            week_type: 1,
            date_from: '2024-01-01',
            date_to: '2024-12-31',
            active: true,
            create_date: '2024-01-04 13:00:00',
            write_date: '2024-01-18 17:30:00'
        ), // Valid new
    ]);

    $this->odooClient
        ->shouldReceive('getScheduleDetails')
        ->once()
        ->andReturn($scheduleDetailsData);

    // Execute the job
    $this->job->handle();

    // Verify data integrity
    expect(ScheduleDetail::count())->toBe(3); // 2 existing + 1 new valid

    // Existing detail should be updated
    $updated = ScheduleDetail::where('odoo_detail_id', 1)->first();
    expect($updated->name)->toBe('Updated Existing 1');
    expect($updated->weekday)->toBe(0);
    expect($updated->start->format('H:i:s'))->toBe('09:00:00');
    expect($updated->active)->toBe(false);

    // Second existing detail should remain unchanged
    $unchanged = ScheduleDetail::where('odoo_detail_id', 2)->first();
    expect($unchanged->name)->toBe('Existing 2');
    expect($unchanged->weekday)->toBe(2);
    expect($unchanged->active)->toBe(false);

    // New valid detail should be created
    $newDetail = ScheduleDetail::where('odoo_detail_id', 4)->first();
    expect($newDetail->name)->toBe('Valid New Detail');
    expect($newDetail->odoo_schedule_id)->toBe(2);
    expect($newDetail->weekday)->toBe(4);
    expect($newDetail->start->format('H:i:s'))->toBe('11:00:00');
    expect($newDetail->week_type)->toBe(1);

    // Invalid detail should not exist
    expect(ScheduleDetail::where('name', 'Invalid New')->exists())->toBe(false);
});

test('SyncOdooScheduleDetailsJob extends BaseSyncJob', function (): void {
    expect($this->job)->toBeInstanceOf(\App\Jobs\Sync\BaseSyncJob::class);
});

test('SyncOdooScheduleDetailsJob has correct priority', function (): void {
    expect($this->job->priority)->toBe(2);
});

afterEach(function (): void {
    Mockery::close();
});
