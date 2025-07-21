<?php

declare(strict_types=1);

use App\Actions\Odoo\CheckOdooHealthAction;
use App\Clients\OdooApiClient;
use App\DataTransferObjects\Odoo\OdooLeaveTypeDTO;
use App\Jobs\Sync\Odoo\SyncOdooLeaveTypesJob;
use App\Models\LeaveType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var \App\Clients\OdooApiClient&\Mockery\MockInterface */
    $this->odooClient = Mockery::namedMock('MockOdooApiClient', OdooApiClient::class);
    $this->job = new SyncOdooLeaveTypesJob($this->odooClient);
});

test('SyncOdooLeaveTypesJob can be constructed with OdooApiClient', function (): void {
    expect($this->job)->toBeInstanceOf(SyncOdooLeaveTypesJob::class);
    expect($this->job->priority)->toBe(2);
});

test('SyncOdooLeaveTypesJob handle method fetches and processes leave types', function (): void {
    // Mock leave types data
    $leaveTypesData = collect([
        new OdooLeaveTypeDTO(
            id: 1,
            name: 'Paid Time Off',
            request_unit: 'day',
            active: true,
            create_date: '2024-01-01 10:00:00',
            write_date: '2024-01-15 14:30:00'
        ),
        new OdooLeaveTypeDTO(
            id: 2,
            name: 'Sick Leave',
            request_unit: 'half_day',
            active: true,
            create_date: '2024-01-02 11:00:00',
            write_date: '2024-01-16 15:30:00'
        ),
        new OdooLeaveTypeDTO(
            id: 3,
            name: 'Maternity Leave',
            request_unit: 'hour',
            active: false,
            create_date: '2024-01-03 12:00:00',
            write_date: '2024-01-17 16:30:00'
        ),
    ]);

    // Mock the API client to return our test data
    $this->odooClient
        ->shouldReceive('getLeaveTypes')
        ->once()
        ->andReturn($leaveTypesData);

    // Execute the job
    $this->job->handle();

    // Verify that leave types were created in the database
    expect(LeaveType::count())->toBe(3);

    $pto = LeaveType::where('odoo_leave_type_id', 1)->first();
    expect($pto->name)->toBe('Paid Time Off');
    expect($pto->request_unit)->toBe('day');
    expect($pto->active)->toBe(true);
    expect($pto->odoo_created_at)->toBe('2024-01-01 10:00:00');
    expect($pto->odoo_updated_at)->toBe('2024-01-15 14:30:00');

    $sick = LeaveType::where('odoo_leave_type_id', 2)->first();
    expect($sick->name)->toBe('Sick Leave');
    expect($sick->request_unit)->toBe('half_day');
    expect($sick->active)->toBe(true);

    $maternity = LeaveType::where('odoo_leave_type_id', 3)->first();
    expect($maternity->name)->toBe('Maternity Leave');
    expect($maternity->request_unit)->toBe('hour');
    expect($maternity->active)->toBe(false);
});

test('SyncOdooLeaveTypesJob handle method works with empty leave types collection', function (): void {
    // Mock empty collection
    $this->odooClient
        ->shouldReceive('getLeaveTypes')
        ->once()
        ->andReturn(collect([]));

    // Execute the job
    $this->job->handle();

    // Verify no leave types were created
    expect(LeaveType::count())->toBe(0);
});

test('SyncOdooLeaveTypesJob handle method processes single leave type', function (): void {
    // Mock single leave type
    $leaveTypesData = collect([
        new OdooLeaveTypeDTO(
            id: 1,
            name: 'Paid Time Off',
            request_unit: 'day',
            active: true,
            create_date: '2024-01-01 10:00:00',
            write_date: '2024-01-15 14:30:00'
        ),
    ]);

    $this->odooClient
        ->shouldReceive('getLeaveTypes')
        ->once()
        ->andReturn($leaveTypesData);

    // Execute the job
    $this->job->handle();

    // Verify leave type was created
    expect(LeaveType::count())->toBe(1);

    $leaveType = LeaveType::where('odoo_leave_type_id', 1)->first();
    expect($leaveType->name)->toBe('Paid Time Off');
    expect($leaveType->request_unit)->toBe('day');
    expect($leaveType->active)->toBe(true);
});

test('SyncOdooLeaveTypesJob handle method updates existing leave types', function (): void {
    // Create existing leave type
    LeaveType::create([
        'odoo_leave_type_id' => 1,
        'name' => 'Old Name',
        'request_unit' => 'hour',
        'active' => false,
        'odoo_created_at' => '2023-01-01 10:00:00',
        'odoo_updated_at' => '2023-01-01 10:00:00',
    ]);

    // Mock updated leave type data
    $leaveTypesData = collect([
        new OdooLeaveTypeDTO(
            id: 1,
            name: 'Updated Paid Time Off',
            request_unit: 'day',
            active: true,
            create_date: '2024-01-01 10:00:00',
            write_date: '2024-01-15 14:30:00'
        ),
    ]);

    $this->odooClient
        ->shouldReceive('getLeaveTypes')
        ->once()
        ->andReturn($leaveTypesData);

    // Execute the job
    $this->job->handle();

    // Verify leave type was updated, not duplicated
    expect(LeaveType::count())->toBe(1);

    $leaveType = LeaveType::where('odoo_leave_type_id', 1)->first();
    expect($leaveType->name)->toBe('Updated Paid Time Off');
    expect($leaveType->request_unit)->toBe('day');
    expect($leaveType->active)->toBe(true);
    expect($leaveType->odoo_created_at)->toBe('2024-01-01 10:00:00');
    expect($leaveType->odoo_updated_at)->toBe('2024-01-15 14:30:00');
});

test('SyncOdooLeaveTypesJob failed method triggers health check', function (): void {
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

test('SyncOdooLeaveTypesJob can be dispatched to queue', function (): void {
    Queue::fake();

    // Dispatch the job
    SyncOdooLeaveTypesJob::dispatch($this->odooClient);

    // Assert job was pushed to queue
    Queue::assertPushed(SyncOdooLeaveTypesJob::class);
});

test('SyncOdooLeaveTypesJob handles API exceptions gracefully', function (): void {
    // Mock API client to throw exception
    $this->odooClient
        ->shouldReceive('getLeaveTypes')
        ->once()
        ->andThrow(new \App\Exceptions\ApiConnectionException('Connection failed'));

    // Execute the job and expect exception
    expect(fn () => $this->job->handle())
        ->toThrow(\App\Exceptions\ApiConnectionException::class, 'Connection failed');

    // Verify no leave types were created
    expect(LeaveType::count())->toBe(0);
});

test('SyncOdooLeaveTypesJob skips invalid leave type data', function (): void {
    // Mock leave types with one invalid entry (missing required fields)
    $leaveTypesData = collect([
        new OdooLeaveTypeDTO(
            id: 1,
            name: 'Valid Leave Type',
            request_unit: 'day',
            active: true,
            create_date: '2024-01-01 10:00:00',
            write_date: '2024-01-15 14:30:00'
        ),
        new OdooLeaveTypeDTO(
            id: null, // Invalid - missing ID
            name: 'Invalid Leave Type',
            request_unit: 'day',
            active: true,
            create_date: '2024-01-01 10:00:00',
            write_date: '2024-01-15 14:30:00'
        ),
        new OdooLeaveTypeDTO(
            id: 3,
            name: 'Another Valid Leave Type',
            request_unit: 'hour',
            active: false,
            create_date: '2024-01-03 12:00:00',
            write_date: '2024-01-17 16:30:00'
        ),
    ]);

    $this->odooClient
        ->shouldReceive('getLeaveTypes')
        ->once()
        ->andReturn($leaveTypesData);

    // Execute the job
    $this->job->handle();

    // Verify only valid leave types were created (invalid one skipped)
    expect(LeaveType::count())->toBe(2);

    expect(LeaveType::where('odoo_leave_type_id', 1)->exists())->toBe(true);
    expect(LeaveType::where('odoo_leave_type_id', 3)->exists())->toBe(true);
    expect(LeaveType::where('name', 'Invalid Leave Type')->exists())->toBe(false);
});

test('SyncOdooLeaveTypesJob handles different request units', function (): void {
    // Mock leave types with different request units
    $leaveTypesData = collect([
        new OdooLeaveTypeDTO(
            id: 1,
            name: 'Daily Leave',
            request_unit: 'day',
            active: true,
            create_date: '2024-01-01 10:00:00',
            write_date: '2024-01-15 14:30:00'
        ),
        new OdooLeaveTypeDTO(
            id: 2,
            name: 'Half Day Leave',
            request_unit: 'half_day',
            active: true,
            create_date: '2024-01-02 11:00:00',
            write_date: '2024-01-16 15:30:00'
        ),
        new OdooLeaveTypeDTO(
            id: 3,
            name: 'Hourly Leave',
            request_unit: 'hour',
            active: true,
            create_date: '2024-01-03 12:00:00',
            write_date: '2024-01-17 16:30:00'
        ),
        new OdooLeaveTypeDTO(
            id: 4,
            name: 'No Unit Leave',
            request_unit: null,
            active: true,
            create_date: '2024-01-04 13:00:00',
            write_date: '2024-01-18 17:30:00'
        ),
    ]);

    $this->odooClient
        ->shouldReceive('getLeaveTypes')
        ->once()
        ->andReturn($leaveTypesData);

    // Execute the job
    $this->job->handle();

    // Verify all leave types were created with correct request units
    expect(LeaveType::count())->toBe(4);

    $daily = LeaveType::where('odoo_leave_type_id', 1)->first();
    expect($daily->request_unit)->toBe('day');

    $halfDay = LeaveType::where('odoo_leave_type_id', 2)->first();
    expect($halfDay->request_unit)->toBe('half_day');

    $hourly = LeaveType::where('odoo_leave_type_id', 3)->first();
    expect($hourly->request_unit)->toBe('hour');

    $noUnit = LeaveType::where('odoo_leave_type_id', 4)->first();
    expect($noUnit->request_unit)->toBeNull();
});

test('SyncOdooLeaveTypesJob processes large number of leave types efficiently', function (): void {
    // Create a large collection of leave types
    $leaveTypesData = collect();
    for ($i = 1; $i <= 50; $i++) {
        $units = ['day', 'half_day', 'hour'];
        $leaveTypesData->push(new OdooLeaveTypeDTO(
            id: $i,
            name: "Leave Type {$i}",
            request_unit: $units[$i % 3],
            active: ($i % 4 !== 0), // Most active, some inactive
            create_date: '2024-01-01 10:00:00',
            write_date: '2024-01-15 14:30:00'
        ));
    }

    $this->odooClient
        ->shouldReceive('getLeaveTypes')
        ->once()
        ->andReturn($leaveTypesData);

    // Execute the job
    $this->job->handle();

    // Verify all leave types were processed
    expect(LeaveType::count())->toBe(50);

    // Verify some random samples
    $type25 = LeaveType::where('odoo_leave_type_id', 25)->first();
    expect($type25->name)->toBe('Leave Type 25');
    expect($type25->request_unit)->toBe('half_day'); // 25 % 3 = 1, so index 1 = 'half_day'
    expect($type25->active)->toBe(true);

    $type24 = LeaveType::where('odoo_leave_type_id', 24)->first();
    expect($type24->active)->toBe(false); // 24 % 4 === 0
});

test('SyncOdooLeaveTypesJob maintains data integrity during partial failures', function (): void {
    // Create some existing leave types
    LeaveType::create(['odoo_leave_type_id' => 1, 'name' => 'Existing 1', 'active' => true]);
    LeaveType::create(['odoo_leave_type_id' => 2, 'name' => 'Existing 2', 'active' => false]);

    // Mock leave types with mix of valid and invalid data
    $leaveTypesData = collect([
        new OdooLeaveTypeDTO(
            id: 1,
            name: 'Updated Existing 1',
            request_unit: 'day',
            active: false,
            create_date: '2024-01-01 10:00:00',
            write_date: '2024-01-15 14:30:00'
        ), // Update existing
        new OdooLeaveTypeDTO(
            id: null, // Invalid - missing ID
            name: 'Invalid New',
            request_unit: 'day',
            active: true,
            create_date: '2024-01-01 10:00:00',
            write_date: '2024-01-15 14:30:00'
        ), // Invalid new
        new OdooLeaveTypeDTO(
            id: 4,
            name: 'Valid New Leave Type',
            request_unit: 'hour',
            active: true,
            create_date: '2024-01-04 13:00:00',
            write_date: '2024-01-18 17:30:00'
        ), // Valid new
    ]);

    $this->odooClient
        ->shouldReceive('getLeaveTypes')
        ->once()
        ->andReturn($leaveTypesData);

    // Execute the job
    $this->job->handle();

    // Verify data integrity
    expect(LeaveType::count())->toBe(3); // 2 existing + 1 new valid

    // Existing leave type should be updated
    $updated = LeaveType::where('odoo_leave_type_id', 1)->first();
    expect($updated->name)->toBe('Updated Existing 1');
    expect($updated->request_unit)->toBe('day');
    expect($updated->active)->toBe(false);

    // Second existing leave type should remain unchanged
    $unchanged = LeaveType::where('odoo_leave_type_id', 2)->first();
    expect($unchanged->name)->toBe('Existing 2');
    expect($unchanged->active)->toBe(false);

    // New valid leave type should be created
    $newType = LeaveType::where('odoo_leave_type_id', 4)->first();
    expect($newType->name)->toBe('Valid New Leave Type');
    expect($newType->request_unit)->toBe('hour');
    expect($newType->active)->toBe(true);

    // Invalid leave type should not exist
    expect(LeaveType::where('name', 'Invalid New')->exists())->toBe(false);
});

test('SyncOdooLeaveTypesJob extends BaseSyncJob', function (): void {
    expect($this->job)->toBeInstanceOf(\App\Jobs\Sync\BaseSyncJob::class);
});

test('SyncOdooLeaveTypesJob has correct priority', function (): void {
    expect($this->job->priority)->toBe(2);
});

afterEach(function (): void {
    Mockery::close();
});
