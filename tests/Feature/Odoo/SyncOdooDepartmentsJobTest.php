<?php

declare(strict_types=1);

use App\Actions\Odoo\CheckOdooHealthAction;
use App\Clients\OdooApiClient;
use App\DataTransferObjects\Odoo\OdooDepartmentDTO;
use App\Jobs\Sync\Odoo\SyncOdooDepartmentsJob;
use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var \App\Clients\OdooApiClient&\Mockery\MockInterface */
    $this->odooClient = Mockery::namedMock('MockOdooApiClient', OdooApiClient::class);
    $this->job = new SyncOdooDepartmentsJob($this->odooClient);
});

test('SyncOdooDepartmentsJob can be constructed with OdooApiClient', function (): void {
    expect($this->job)->toBeInstanceOf(SyncOdooDepartmentsJob::class);
    expect($this->job->priority)->toBe(1);
});

test('SyncOdooDepartmentsJob handle method fetches and processes departments', function (): void {
    // Mock departments data
    $departmentsData = collect([
        new OdooDepartmentDTO(
            id: 1,
            name: 'Engineering',
            active: true,
            manager_id: [5, 'John Manager'],
            parent_id: [10, 'Company']
        ),
        new OdooDepartmentDTO(
            id: 2,
            name: 'Marketing',
            active: true,
            manager_id: [6, 'Jane Manager'],
            parent_id: [10, 'Company']
        ),
        new OdooDepartmentDTO(
            id: 3,
            name: 'HR',
            active: false,
            manager_id: null,
            parent_id: null
        ),
    ]);

    // Mock the API client to return our test data
    $this->odooClient
        ->shouldReceive('getDepartments')
        ->once()
        ->andReturn($departmentsData);

    // Execute the job
    $this->job->handle();

    // Verify that departments were created in the database
    expect(Department::count())->toBe(3);

    $engineering = Department::where('odoo_department_id', 1)->first();
    expect($engineering->name)->toBe('Engineering');
    expect($engineering->active)->toBe(true);
    expect($engineering->odoo_manager_id)->toBe(5);
    expect($engineering->odoo_parent_department_id)->toBe(10);

    $marketing = Department::where('odoo_department_id', 2)->first();
    expect($marketing->name)->toBe('Marketing');
    expect($marketing->active)->toBe(true);
    expect($marketing->odoo_manager_id)->toBe(6);
    expect($marketing->odoo_parent_department_id)->toBe(10);

    $hr = Department::where('odoo_department_id', 3)->first();
    expect($hr->name)->toBe('HR');
    expect($hr->active)->toBe(false);
    expect($hr->odoo_manager_id)->toBeNull();
    expect($hr->odoo_parent_department_id)->toBeNull();
});

test('SyncOdooDepartmentsJob handle method works with empty departments collection', function (): void {
    // Mock empty collection
    $this->odooClient
        ->shouldReceive('getDepartments')
        ->once()
        ->andReturn(collect([]));

    // Execute the job
    $this->job->handle();

    // Verify no departments were created
    expect(Department::count())->toBe(0);
});

test('SyncOdooDepartmentsJob handle method processes single department', function (): void {
    // Mock single department
    $departmentsData = collect([
        new OdooDepartmentDTO(
            id: 1,
            name: 'Engineering',
            active: true,
            manager_id: [5, 'John Manager'],
            parent_id: null
        ),
    ]);

    $this->odooClient
        ->shouldReceive('getDepartments')
        ->once()
        ->andReturn($departmentsData);

    // Execute the job
    $this->job->handle();

    // Verify department was created
    expect(Department::count())->toBe(1);

    $department = Department::where('odoo_department_id', 1)->first();
    expect($department->name)->toBe('Engineering');
    expect($department->active)->toBe(true);
    expect($department->odoo_manager_id)->toBe(5);
    expect($department->odoo_parent_department_id)->toBeNull();
});

test('SyncOdooDepartmentsJob handle method updates existing departments', function (): void {
    // Create existing department
    Department::create([
        'odoo_department_id' => 1,
        'name' => 'Old Name',
        'active' => false,
        'odoo_manager_id' => 99,
        'odoo_parent_department_id' => 88,
    ]);

    // Mock updated department data
    $departmentsData = collect([
        new OdooDepartmentDTO(
            id: 1,
            name: 'Updated Engineering',
            active: true,
            manager_id: [5, 'New Manager'],
            parent_id: [10, 'New Parent']
        ),
    ]);

    $this->odooClient
        ->shouldReceive('getDepartments')
        ->once()
        ->andReturn($departmentsData);

    // Execute the job
    $this->job->handle();

    // Verify department was updated, not duplicated
    expect(Department::count())->toBe(1);

    $department = Department::where('odoo_department_id', 1)->first();
    expect($department->name)->toBe('Updated Engineering');
    expect($department->active)->toBe(true);
    expect($department->odoo_manager_id)->toBe(5);
    expect($department->odoo_parent_department_id)->toBe(10);
});

test('SyncOdooDepartmentsJob failed method triggers health check', function (): void {
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

test('SyncOdooDepartmentsJob can be dispatched to queue', function (): void {
    Queue::fake();

    // Dispatch the job
    SyncOdooDepartmentsJob::dispatch($this->odooClient);

    // Assert job was pushed to queue
    Queue::assertPushed(SyncOdooDepartmentsJob::class);
});

test('SyncOdooDepartmentsJob handles API exceptions gracefully', function (): void {
    // Mock API client to throw exception
    $this->odooClient
        ->shouldReceive('getDepartments')
        ->once()
        ->andThrow(new \App\Exceptions\ApiConnectionException('Connection failed'));

    // Execute the job and expect exception
    expect(fn () => $this->job->handle())
        ->toThrow(\App\Exceptions\ApiConnectionException::class, 'Connection failed');

    // Verify no departments were created
    expect(Department::count())->toBe(0);
});

test('SyncOdooDepartmentsJob skips invalid department data', function (): void {
    // Mock departments with one invalid entry (missing required fields)
    $departmentsData = collect([
        new OdooDepartmentDTO(
            id: 1,
            name: 'Valid Department',
            active: true,
            manager_id: [5, 'Manager'],
            parent_id: null
        ),
        new OdooDepartmentDTO(
            id: 2,
            name: null, // Invalid - missing name
            active: true,
            manager_id: null,
            parent_id: null
        ),
        new OdooDepartmentDTO(
            id: 3,
            name: 'Another Valid Department',
            active: false,
            manager_id: null,
            parent_id: null
        ),
    ]);

    $this->odooClient
        ->shouldReceive('getDepartments')
        ->once()
        ->andReturn($departmentsData);

    // Execute the job
    $this->job->handle();

    // Verify only valid departments were created (invalid one skipped)
    expect(Department::count())->toBe(2);

    expect(Department::where('odoo_department_id', 1)->exists())->toBe(true);
    expect(Department::where('odoo_department_id', 3)->exists())->toBe(true);
    expect(Department::where('odoo_department_id', 2)->exists())->toBe(false);
});

test('SyncOdooDepartmentsJob handles hierarchical department structures', function (): void {
    // Mock hierarchical departments (parent-child relationships)
    $departmentsData = collect([
        new OdooDepartmentDTO(
            id: 1,
            name: 'Company',
            active: true,
            manager_id: [1, 'CEO'],
            parent_id: null // Root department
        ),
        new OdooDepartmentDTO(
            id: 2,
            name: 'Engineering',
            active: true,
            manager_id: [2, 'Engineering Manager'],
            parent_id: [1, 'Company'] // Child of Company
        ),
        new OdooDepartmentDTO(
            id: 3,
            name: 'Frontend Team',
            active: true,
            manager_id: [3, 'Frontend Lead'],
            parent_id: [2, 'Engineering'] // Child of Engineering
        ),
    ]);

    $this->odooClient
        ->shouldReceive('getDepartments')
        ->once()
        ->andReturn($departmentsData);

    // Execute the job
    $this->job->handle();

    // Verify all departments were created with correct hierarchy
    expect(Department::count())->toBe(3);

    $company = Department::where('odoo_department_id', 1)->first();
    expect($company->name)->toBe('Company');
    expect($company->odoo_parent_department_id)->toBeNull();

    $engineering = Department::where('odoo_department_id', 2)->first();
    expect($engineering->name)->toBe('Engineering');
    expect($engineering->odoo_parent_department_id)->toBe(1);

    $frontend = Department::where('odoo_department_id', 3)->first();
    expect($frontend->name)->toBe('Frontend Team');
    expect($frontend->odoo_parent_department_id)->toBe(2);
});

test('SyncOdooDepartmentsJob processes large number of departments efficiently', function (): void {
    // Create a large collection of departments
    $departmentsData = collect();
    for ($i = 1; $i <= 50; $i++) {
        $departmentsData->push(new OdooDepartmentDTO(
            id: $i,
            name: "Department {$i}",
            active: ($i % 3 !== 0), // Most active, some inactive
            manager_id: [100 + $i, "Manager {$i}"],
            parent_id: $i > 10 ? [($i % 10) + 1, 'Parent'] : null // Some have parents
        ));
    }

    $this->odooClient
        ->shouldReceive('getDepartments')
        ->once()
        ->andReturn($departmentsData);

    // Execute the job
    $this->job->handle();

    // Verify all departments were processed
    expect(Department::count())->toBe(50);

    // Verify some random samples
    $dept25 = Department::where('odoo_department_id', 25)->first();
    expect($dept25->name)->toBe('Department 25');
    expect($dept25->active)->toBe(true);
    expect($dept25->odoo_manager_id)->toBe(125);

    $dept30 = Department::where('odoo_department_id', 30)->first();
    expect($dept30->name)->toBe('Department 30');
    expect($dept30->active)->toBe(false); // 30 % 3 === 0
});

test('SyncOdooDepartmentsJob maintains data integrity during partial failures', function (): void {
    // Create some existing departments
    Department::create(['odoo_department_id' => 1, 'name' => 'Existing 1', 'active' => true]);
    Department::create(['odoo_department_id' => 2, 'name' => 'Existing 2', 'active' => false]);

    // Mock departments with mix of valid and invalid data
    $departmentsData = collect([
        new OdooDepartmentDTO(
            id: 1,
            name: 'Updated Existing 1',
            active: false,
            manager_id: [42, 'New Manager'],
            parent_id: null
        ), // Update existing
        new OdooDepartmentDTO(
            id: 3,
            name: null, // Invalid - missing name
            active: true,
            manager_id: null,
            parent_id: null
        ), // Invalid new
        new OdooDepartmentDTO(
            id: 4,
            name: 'Valid New Department',
            active: true,
            manager_id: [50, 'Manager'],
            parent_id: [1, 'Parent']
        ), // Valid new
    ]);

    $this->odooClient
        ->shouldReceive('getDepartments')
        ->once()
        ->andReturn($departmentsData);

    // Execute the job
    $this->job->handle();

    // Verify data integrity
    expect(Department::count())->toBe(3); // 2 existing + 1 new valid

    // Existing department should be updated
    $updated = Department::where('odoo_department_id', 1)->first();
    expect($updated->name)->toBe('Updated Existing 1');
    expect($updated->active)->toBe(false);
    expect($updated->odoo_manager_id)->toBe(42);

    // Second existing department should remain unchanged
    $unchanged = Department::where('odoo_department_id', 2)->first();
    expect($unchanged->name)->toBe('Existing 2');
    expect($unchanged->active)->toBe(false);

    // New valid department should be created
    $newDept = Department::where('odoo_department_id', 4)->first();
    expect($newDept->name)->toBe('Valid New Department');
    expect($newDept->active)->toBe(true);
    expect($newDept->odoo_manager_id)->toBe(50);
    expect($newDept->odoo_parent_department_id)->toBe(1);

    // Invalid department should not exist
    expect(Department::where('odoo_department_id', 3)->exists())->toBe(false);
});

test('SyncOdooDepartmentsJob extends BaseSyncJob', function (): void {
    expect($this->job)->toBeInstanceOf(\App\Jobs\Sync\BaseSyncJob::class);
});

test('SyncOdooDepartmentsJob has correct priority', function (): void {
    expect($this->job->priority)->toBe(1);
});

afterEach(function (): void {
    Mockery::close();
});
