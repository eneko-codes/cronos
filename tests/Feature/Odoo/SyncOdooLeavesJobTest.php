<?php

declare(strict_types=1);

use App\Actions\Odoo\CheckOdooHealthAction;
use App\Clients\OdooApiClient;
use App\DataTransferObjects\Odoo\OdooLeaveDTO;
use App\Jobs\Sync\Odoo\SyncOdooLeavesJob;
use App\Models\Category;
use App\Models\Department;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\UserLeave;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var OdooApiClient $odooClient */
    $this->odooClient = Mockery::namedMock('MockOdooApiClient', OdooApiClient::class);
    $this->fromDate = '2024-01-01';
    $this->toDate = '2024-12-31';
    $this->job = new SyncOdooLeavesJob($this->odooClient, $this->fromDate, $this->toDate);

    // Create test data
    $this->user = User::factory()->create([
        'odoo_id' => 123,
        'email' => 'test@company.com',
        'name' => 'Test User',
    ]);
    $this->leaveType = LeaveType::create([
        'odoo_leave_type_id' => 1,
        'name' => 'Paid Time Off',
        'active' => true,
    ]);
    $this->department = Department::create([
        'odoo_department_id' => 5,
        'name' => 'Engineering',
        'active' => true,
    ]);
    $this->category = Category::create([
        'odoo_category_id' => 10,
        'name' => 'Full Time',
        'active' => true,
    ]);
});

test('SyncOdooLeavesJob can be constructed with OdooApiClient', function (): void {
    expect($this->job)->toBeInstanceOf(SyncOdooLeavesJob::class);
    expect($this->job->priority)->toBe(2);
});

test('SyncOdooLeavesJob handle method fetches and processes leaves', function (): void {
    // Mock leaves data with different types
    $leavesData = collect([
        new OdooLeaveDTO(
            id: 1,
            holiday_type: 'employee',
            date_from: '2024-07-01 09:00:00',
            date_to: '2024-07-05 18:00:00',
            number_of_days: 5.0,
            state: 'validate',
            holiday_status_id: [1, 'Paid Time Off'],
            request_hour_from: 9.0,
            request_hour_to: 18.0,
            employee_id: [123, 'John Doe'],
            category_id: null,
            department_id: null
        ),
        new OdooLeaveDTO(
            id: 2,
            holiday_type: 'category',
            date_from: '2024-08-01 10:00:00',
            date_to: '2024-08-01 15:00:00',
            number_of_days: 0.5,
            state: 'confirm',
            holiday_status_id: [1, 'Paid Time Off'],
            request_hour_from: 10.0,
            request_hour_to: 15.0,
            employee_id: null,
            category_id: [10, 'Full Time'],
            department_id: null
        ),
        new OdooLeaveDTO(
            id: 3,
            holiday_type: 'department',
            date_from: '2024-09-01 08:00:00',
            date_to: '2024-09-03 17:00:00',
            number_of_days: 3.0,
            state: 'draft',
            holiday_status_id: [1, 'Paid Time Off'],
            request_hour_from: 8.0,
            request_hour_to: 17.0,
            employee_id: null,
            category_id: null,
            department_id: [5, 'Engineering']
        ),
    ]);

    // Mock the API client to return our test data
    $this->odooClient
        ->shouldReceive('getLeaves')
        ->with($this->fromDate, $this->toDate)
        ->once()
        ->andReturn($leavesData);

    // Execute the job
    $this->job->handle();

    // Verify that leaves were created in the database
    expect(UserLeave::count())->toBe(3);

    $employeeLeave = UserLeave::where('odoo_leave_id', 1)->first();
    expect($employeeLeave->type)->toBe('employee');
    expect($employeeLeave->user_id)->toBe($this->user->id);
    expect($employeeLeave->leave_type_id)->toBe(1);
    expect($employeeLeave->start_date->format('Y-m-d H:i:s'))->toBe('2024-07-01 09:00:00');
    expect($employeeLeave->end_date->format('Y-m-d H:i:s'))->toBe('2024-07-05 18:00:00');
    expect($employeeLeave->duration_days)->toBe(5.0);
    expect($employeeLeave->status)->toBe('validate');
    expect($employeeLeave->request_hour_from)->toBe(9.0);
    expect($employeeLeave->request_hour_to)->toBe(18.0);
    expect($employeeLeave->category_id)->toBeNull();
    expect($employeeLeave->department_id)->toBeNull();

    $categoryLeave = UserLeave::where('odoo_leave_id', 2)->first();
    expect($categoryLeave->type)->toBe('category');
    expect($categoryLeave->user_id)->toBeNull();
    expect($categoryLeave->category_id)->toBe(10);
    expect($categoryLeave->duration_days)->toBe(0.5);
    expect($categoryLeave->status)->toBe('confirm');

    $departmentLeave = UserLeave::where('odoo_leave_id', 3)->first();
    expect($departmentLeave->type)->toBe('department');
    expect($departmentLeave->user_id)->toBeNull();
    expect($departmentLeave->department_id)->toBe(5);
    expect($departmentLeave->duration_days)->toBe(3.0);
    expect($departmentLeave->status)->toBe('draft');
});

test('SyncOdooLeavesJob handle method works with empty leaves collection', function (): void {
    // Mock empty collection
    $this->odooClient
        ->shouldReceive('getLeaves')
        ->with($this->fromDate, $this->toDate)
        ->once()
        ->andReturn(collect([]));

    // Execute the job
    $this->job->handle();

    // Verify no leaves were created
    expect(UserLeave::count())->toBe(0);
});

test('SyncOdooLeavesJob handle method processes single leave', function (): void {
    // Mock single leave
    $leavesData = collect([
        new OdooLeaveDTO(
            id: 1,
            holiday_type: 'employee',
            date_from: '2024-07-01 09:00:00',
            date_to: '2024-07-05 18:00:00',
            number_of_days: 5.0,
            state: 'validate',
            holiday_status_id: [1, 'Paid Time Off'],
            request_hour_from: 9.0,
            request_hour_to: 18.0,
            employee_id: [123, 'John Doe'],
            category_id: null,
            department_id: null
        ),
    ]);

    $this->odooClient
        ->shouldReceive('getLeaves')
        ->with($this->fromDate, $this->toDate)
        ->once()
        ->andReturn($leavesData);

    // Execute the job
    $this->job->handle();

    // Verify leave was created
    expect(UserLeave::count())->toBe(1);

    $leave = UserLeave::where('odoo_leave_id', 1)->first();
    expect($leave->type)->toBe('employee');
    expect($leave->user_id)->toBe($this->user->id);
    expect($leave->leave_type_id)->toBe(1);
    expect($leave->status)->toBe('validate');
});

test('SyncOdooLeavesJob handle method updates existing leaves', function (): void {
    // Create existing leave
    UserLeave::create([
        'odoo_leave_id' => 1,
        'type' => 'employee',
        'user_id' => $this->user->id,
        'leave_type_id' => 1,
        'start_date' => '2024-06-01 09:00:00',
        'end_date' => '2024-06-03 18:00:00',
        'duration_days' => 3.0,
        'status' => 'draft',
        'request_hour_from' => 9.0,
        'request_hour_to' => 18.0,
    ]);

    // Mock updated leave data
    $leavesData = collect([
        new OdooLeaveDTO(
            id: 1,
            holiday_type: 'employee',
            date_from: '2024-07-01 09:00:00',
            date_to: '2024-07-05 18:00:00',
            number_of_days: 5.0,
            state: 'validate',
            holiday_status_id: [1, 'Paid Time Off'],
            request_hour_from: 9.0,
            request_hour_to: 18.0,
            employee_id: [123, 'John Doe'],
            category_id: null,
            department_id: null
        ),
    ]);

    $this->odooClient
        ->shouldReceive('getLeaves')
        ->once()
        ->andReturn($leavesData);

    // Execute the job
    $this->job->handle();

    // Verify leave was updated, not duplicated
    expect(UserLeave::count())->toBe(1);

    $leave = UserLeave::where('odoo_leave_id', 1)->first();
    expect($leave->start_date->format('Y-m-d H:i:s'))->toBe('2024-07-01 09:00:00');
    expect($leave->end_date->format('Y-m-d H:i:s'))->toBe('2024-07-05 18:00:00');
    expect($leave->duration_days)->toBe(5.0);
    expect($leave->status)->toBe('validate');
});

test('SyncOdooLeavesJob failed method triggers health check', function (): void {
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

test('SyncOdooLeavesJob can be dispatched to queue', function (): void {
    Queue::fake();

    // Dispatch the job
    SyncOdooLeavesJob::dispatch($this->odooClient, $this->fromDate, $this->toDate);

    // Assert job was pushed to queue
    Queue::assertPushed(SyncOdooLeavesJob::class);
});

test('SyncOdooLeavesJob handles API exceptions gracefully', function (): void {
    // Mock API client to throw exception
    $this->odooClient
        ->shouldReceive('getLeaves')
        ->once()
        ->andThrow(new \App\Exceptions\ApiConnectionException('Connection failed'));

    // Execute the job and expect exception
    expect(fn () => $this->job->handle())
        ->toThrow(\App\Exceptions\ApiConnectionException::class, 'Connection failed');

    // Verify no leaves were created
    expect(UserLeave::count())->toBe(0);
});

test('SyncOdooLeavesJob skips invalid leave data', function (): void {
    // Mock leaves with one invalid entry (missing required fields)
    $leavesData = collect([
        new OdooLeaveDTO(
            id: 1,
            holiday_type: 'employee',
            date_from: '2024-07-01 09:00:00',
            date_to: '2024-07-05 18:00:00',
            number_of_days: 5.0,
            state: 'validate',
            holiday_status_id: [1, 'Paid Time Off'],
            request_hour_from: 9.0,
            request_hour_to: 18.0,
            employee_id: [123, 'John Doe'],
            category_id: null,
            department_id: null
        ),
        new OdooLeaveDTO(
            id: null, // Invalid - missing ID
            holiday_type: 'employee',
            date_from: '2024-08-01 09:00:00',
            date_to: '2024-08-03 18:00:00',
            number_of_days: 3.0,
            state: 'validate',
            holiday_status_id: [1, 'Paid Time Off'],
            request_hour_from: 9.0,
            request_hour_to: 18.0,
            employee_id: [124, 'Jane Doe'],
            category_id: null,
            department_id: null
        ),
        new OdooLeaveDTO(
            id: 3,
            holiday_type: 'category',
            date_from: '2024-09-01 10:00:00',
            date_to: '2024-09-01 15:00:00',
            number_of_days: 0.5,
            state: 'confirm',
            holiday_status_id: [1, 'Paid Time Off'],
            request_hour_from: 10.0,
            request_hour_to: 15.0,
            employee_id: null,
            category_id: [10, 'Full Time'],
            department_id: null
        ),
    ]);

    $this->odooClient
        ->shouldReceive('getLeaves')
        ->once()
        ->andReturn($leavesData);

    // Execute the job
    $this->job->handle();

    // Verify only valid leaves were created (invalid one skipped)
    expect(UserLeave::count())->toBe(2);

    expect(UserLeave::where('odoo_leave_id', 1)->exists())->toBe(true);
    expect(UserLeave::where('odoo_leave_id', 3)->exists())->toBe(true);
    expect(UserLeave::where('user_id', 124)->exists())->toBe(false);
});

test('SyncOdooLeavesJob handles different leave states correctly', function (): void {
    // Mock leaves with different states
    $leavesData = collect([
        new OdooLeaveDTO(
            id: 1,
            holiday_type: 'employee',
            date_from: '2024-07-01 09:00:00',
            date_to: '2024-07-05 18:00:00',
            number_of_days: 5.0,
            state: 'validate',
            holiday_status_id: [1, 'Paid Time Off'],
            request_hour_from: 9.0,
            request_hour_to: 18.0,
            employee_id: [123, 'John Doe'],
            category_id: null,
            department_id: null
        ),
        new OdooLeaveDTO(
            id: 2,
            holiday_type: 'employee',
            date_from: '2024-08-01 09:00:00',
            date_to: '2024-08-03 18:00:00',
            number_of_days: 3.0,
            state: 'validate1',
            holiday_status_id: [1, 'Paid Time Off'],
            request_hour_from: 9.0,
            request_hour_to: 18.0,
            employee_id: [123, 'John Doe'],
            category_id: null,
            department_id: null
        ),
        new OdooLeaveDTO(
            id: 3,
            holiday_type: 'employee',
            date_from: '2024-09-01 09:00:00',
            date_to: '2024-09-01 18:00:00',
            number_of_days: 1.0,
            state: 'refuse',
            holiday_status_id: [1, 'Paid Time Off'],
            request_hour_from: 9.0,
            request_hour_to: 18.0,
            employee_id: [123, 'John Doe'],
            category_id: null,
            department_id: null
        ),
        new OdooLeaveDTO(
            id: 4,
            holiday_type: 'employee',
            date_from: '2024-10-01 09:00:00',
            date_to: '2024-10-01 18:00:00',
            number_of_days: 1.0,
            state: 'cancel',
            holiday_status_id: [1, 'Paid Time Off'],
            request_hour_from: 9.0,
            request_hour_to: 18.0,
            employee_id: [123, 'John Doe'],
            category_id: null,
            department_id: null
        ),
        new OdooLeaveDTO(
            id: 5,
            holiday_type: 'employee',
            date_from: '2024-11-01 09:00:00',
            date_to: '2024-11-01 18:00:00',
            number_of_days: 1.0,
            state: 'draft',
            holiday_status_id: [1, 'Paid Time Off'],
            request_hour_from: 9.0,
            request_hour_to: 18.0,
            employee_id: [123, 'John Doe'],
            category_id: null,
            department_id: null
        ),
        new OdooLeaveDTO(
            id: 6,
            holiday_type: 'employee',
            date_from: '2024-12-01 09:00:00',
            date_to: '2024-12-01 18:00:00',
            number_of_days: 1.0,
            state: 'confirm',
            holiday_status_id: [1, 'Paid Time Off'],
            request_hour_from: 9.0,
            request_hour_to: 18.0,
            employee_id: [123, 'John Doe'],
            category_id: null,
            department_id: null
        ),
    ]);

    $this->odooClient
        ->shouldReceive('getLeaves')
        ->once()
        ->andReturn($leavesData);

    // Execute the job
    $this->job->handle();

    // Verify all leaves were created with correct states
    expect(UserLeave::count())->toBe(6);

    expect(UserLeave::where('odoo_leave_id', 1)->first()->status)->toBe('validate');
    expect(UserLeave::where('odoo_leave_id', 2)->first()->status)->toBe('validate1');
    expect(UserLeave::where('odoo_leave_id', 3)->first()->status)->toBe('refuse');
    expect(UserLeave::where('odoo_leave_id', 4)->first()->status)->toBe('cancel');
    expect(UserLeave::where('odoo_leave_id', 5)->first()->status)->toBe('draft');
    expect(UserLeave::where('odoo_leave_id', 6)->first()->status)->toBe('confirm');
});

test('SyncOdooLeavesJob handles leaves with null optional fields', function (): void {
    // Mock leaves with null optional fields
    $leavesData = collect([
        new OdooLeaveDTO(
            id: 1,
            holiday_type: 'employee',
            date_from: '2024-07-01 09:00:00',
            date_to: '2024-07-05 18:00:00',
            number_of_days: null, // Null optional field
            state: 'validate',
            holiday_status_id: [1, 'Paid Time Off'],
            request_hour_from: null, // Null optional field
            request_hour_to: null, // Null optional field
            employee_id: [123, 'John Doe'],
            category_id: null,
            department_id: null
        ),
        new OdooLeaveDTO(
            id: 2,
            holiday_type: 'category',
            date_from: '2024-08-01 10:00:00',
            date_to: '2024-08-01 15:00:00',
            number_of_days: 0.5,
            state: 'confirm',
            holiday_status_id: null, // Null leave type
            request_hour_from: 10.0,
            request_hour_to: 15.0,
            employee_id: null,
            category_id: [10, 'Full Time'],
            department_id: null
        ),
    ]);

    $this->odooClient
        ->shouldReceive('getLeaves')
        ->once()
        ->andReturn($leavesData);

    // Execute the job
    $this->job->handle();

    // Verify leaves were created with null values handled correctly (both leaves skipped due to validation failures)
    expect(UserLeave::count())->toBe(0);

    // No leaves should be created since both fail validation
    expect(UserLeave::where('odoo_leave_id', 1)->exists())->toBe(false);
    expect(UserLeave::where('odoo_leave_id', 2)->exists())->toBe(false);
});

test('SyncOdooLeavesJob processes large number of leaves efficiently', function (): void {
    // Create additional test users
    $users = User::factory()->count(10)->create();

    // Create a large collection of leaves
    $leavesData = collect();
    for ($i = 1; $i <= 100; $i++) {
        $types = ['employee', 'category', 'department'];
        $states = ['validate', 'validate1', 'refuse', 'cancel', 'draft', 'confirm'];

        $leavesData->push(new OdooLeaveDTO(
            id: $i,
            holiday_type: $types[$i % 3],
            date_from: '2024-07-0'.(($i % 9) + 1).' 09:00:00',
            date_to: '2024-07-0'.(($i % 9) + 1).' 18:00:00',
            number_of_days: 1.0,
            state: $states[$i % 6],
            holiday_status_id: [1, 'Paid Time Off'],
            request_hour_from: 9.0,
            request_hour_to: 18.0,
            employee_id: ($i % 3 === 0) ? [$users[$i % 10]->odoo_id, 'Employee'] : null,
            category_id: ($i % 3 === 1) ? [10, 'Full Time'] : null,
            department_id: ($i % 3 === 2) ? [5, 'Engineering'] : null
        ));
    }

    $this->odooClient
        ->shouldReceive('getLeaves')
        ->once()
        ->andReturn($leavesData);

    // Execute the job
    $this->job->handle();

    // Verify all leaves were processed
    expect(UserLeave::count())->toBe(100);

    // Verify some random samples
    $leave25 = UserLeave::where('odoo_leave_id', 25)->first();
    expect($leave25->type)->toBe('category'); // 25 % 3 = 1, so types[1] = 'category'
    expect($leave25->status)->toBe('validate1'); // states[25 % 6] = states[1] = 'validate1'

    $leave26 = UserLeave::where('odoo_leave_id', 26)->first();
    expect($leave26->type)->toBe('department'); // 26 % 3 = 2, so types[2] = 'department'
});

test('SyncOdooLeavesJob maintains data integrity during partial failures', function (): void {
    // Create some existing leaves
    UserLeave::create([
        'odoo_leave_id' => 1,
        'type' => 'employee',
        'user_id' => $this->user->id,
        'leave_type_id' => 1,
        'start_date' => '2024-06-01 09:00:00',
        'end_date' => '2024-06-03 18:00:00',
        'status' => 'draft',
    ]);
    UserLeave::create([
        'odoo_leave_id' => 2,
        'type' => 'category',
        'category_id' => 10,
        'leave_type_id' => 1,
        'start_date' => '2024-06-05 09:00:00',
        'end_date' => '2024-06-05 18:00:00',
        'status' => 'confirm',
    ]);

    // Mock leaves with mix of valid and invalid data
    $leavesData = collect([
        new OdooLeaveDTO(
            id: 1,
            holiday_type: 'employee',
            date_from: '2024-07-01 09:00:00',
            date_to: '2024-07-05 18:00:00',
            number_of_days: 5.0,
            state: 'validate',
            holiday_status_id: [1, 'Paid Time Off'],
            request_hour_from: 9.0,
            request_hour_to: 18.0,
            employee_id: [123, 'John Doe'],
            category_id: null,
            department_id: null
        ), // Update existing
        new OdooLeaveDTO(
            id: null, // Invalid - missing ID
            holiday_type: 'employee',
            date_from: '2024-08-01 09:00:00',
            date_to: '2024-08-03 18:00:00',
            number_of_days: 3.0,
            state: 'validate',
            holiday_status_id: [1, 'Paid Time Off'],
            request_hour_from: 9.0,
            request_hour_to: 18.0,
            employee_id: [124, 'Jane Doe'],
            category_id: null,
            department_id: null
        ), // Invalid new
        new OdooLeaveDTO(
            id: 4,
            holiday_type: 'department',
            date_from: '2024-09-01 08:00:00',
            date_to: '2024-09-03 17:00:00',
            number_of_days: 3.0,
            state: 'draft',
            holiday_status_id: [1, 'Paid Time Off'],
            request_hour_from: 8.0,
            request_hour_to: 17.0,
            employee_id: null,
            category_id: null,
            department_id: [5, 'Engineering']
        ), // Valid new
    ]);

    $this->odooClient
        ->shouldReceive('getLeaves')
        ->once()
        ->andReturn($leavesData);

    // Execute the job
    $this->job->handle();

    // Verify data integrity
    expect(UserLeave::count())->toBe(3); // 2 existing + 1 new valid

    // Existing leave should be updated
    $updated = UserLeave::where('odoo_leave_id', 1)->first();
    expect($updated->start_date->format('Y-m-d H:i:s'))->toBe('2024-07-01 09:00:00');
    expect($updated->end_date->format('Y-m-d H:i:s'))->toBe('2024-07-05 18:00:00');
    expect($updated->duration_days)->toBe(5.0);
    expect($updated->status)->toBe('validate');

    // Second existing leave should remain unchanged
    $unchanged = UserLeave::where('odoo_leave_id', 2)->first();
    expect($unchanged->start_date->format('Y-m-d H:i:s'))->toBe('2024-06-05 09:00:00');
    expect($unchanged->status)->toBe('confirm');

    // New valid leave should be created
    $newLeave = UserLeave::where('odoo_leave_id', 4)->first();
    expect($newLeave->type)->toBe('department');
    expect($newLeave->department_id)->toBe(5);
    expect($newLeave->duration_days)->toBe(3.0);
    expect($newLeave->status)->toBe('draft');

    // Invalid leave should not exist
    expect(UserLeave::where('user_id', 124)->exists())->toBe(false);
});

test('SyncOdooLeavesJob extends BaseSyncJob', function (): void {
    expect($this->job)->toBeInstanceOf(\App\Jobs\Sync\BaseSyncJob::class);
});

test('SyncOdooLeavesJob has correct priority', function (): void {
    expect($this->job->priority)->toBe(2);
});

afterEach(function (): void {
    Mockery::close();
});
