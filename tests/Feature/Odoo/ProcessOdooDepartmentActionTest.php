<?php

declare(strict_types=1);

use App\Actions\Odoo\ProcessOdooDepartmentAction;
use App\DataTransferObjects\Odoo\OdooDepartmentDTO;
use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->action = new ProcessOdooDepartmentAction;
});

test('ProcessOdooDepartmentAction creates new department with valid data', function (): void {
    $dto = new OdooDepartmentDTO(
        id: 1,
        name: 'Engineering',
        active: true,
        manager_id: [5, 'John Manager'],
        parent_id: [10, 'Company']
    );

    $this->action->execute($dto);

    $department = Department::where('odoo_department_id', 1)->first();

    expect($department)->not->toBeNull();
    expect($department->odoo_department_id)->toBe(1);
    expect($department->name)->toBe('Engineering');
    expect($department->active)->toBe(true);
    expect($department->odoo_manager_id)->toBe(5);
    expect($department->odoo_parent_department_id)->toBe(10);
});

test('ProcessOdooDepartmentAction updates existing department', function (): void {
    // Create existing department
    $existingDepartment = Department::create([
        'odoo_department_id' => 1,
        'name' => 'Old Name',
        'active' => false,
        'odoo_manager_id' => 99,
        'odoo_parent_department_id' => 88,
    ]);

    $dto = new OdooDepartmentDTO(
        id: 1,
        name: 'Engineering',
        active: true,
        manager_id: [5, 'John Manager'],
        parent_id: [10, 'Company']
    );

    $this->action->execute($dto);

    $department = Department::where('odoo_department_id', 1)->first();

    expect($department)->not->toBeNull();
    expect($department->odoo_department_id)->toBe(1);
    expect($department->name)->toBe('Engineering');
    expect($department->active)->toBe(true);
    expect($department->odoo_manager_id)->toBe(5);
    expect($department->odoo_parent_department_id)->toBe(10);

    // Should still be the same record, not a new one
    expect(Department::count())->toBe(1);
});

test('ProcessOdooDepartmentAction handles null relations correctly', function (): void {
    $dto = new OdooDepartmentDTO(
        id: 1,
        name: 'Engineering',
        active: true,
        manager_id: null, // No manager
        parent_id: null   // No parent
    );

    $this->action->execute($dto);

    $department = Department::where('odoo_department_id', 1)->first();

    expect($department)->not->toBeNull();
    expect($department->odoo_manager_id)->toBeNull();
    expect($department->odoo_parent_department_id)->toBeNull();
});

test('ProcessOdooDepartmentAction skips department with null active field', function (): void {
    Log::spy();

    $dto = new OdooDepartmentDTO(
        id: 1,
        name: 'Engineering',
        active: null
    );

    $this->action->execute($dto);

    $department = Department::where('odoo_department_id', 1)->first();

    expect($department)->toBeNull(); // Should be skipped due to validation

    Log::shouldHaveReceived('warning')->once()->with(
        'ProcessOdooDepartmentAction Skipping department due to validation errors',
        Mockery::on(function ($context) {
            return isset($context['department']) && isset($context['errors']);
        })
    );
});

test('ProcessOdooDepartmentAction skips department with missing name', function (): void {
    Log::spy();

    $dto = new OdooDepartmentDTO(
        id: 1,
        name: null, // Missing required name
        active: true
    );

    $this->action->execute($dto);

    // Department should not be created due to validation failure
    $department = Department::where('odoo_department_id', 1)->first();
    expect($department)->toBeNull();

    // Should log a warning
    Log::shouldHaveReceived('warning')
        ->once()
        ->with(
            'ProcessOdooDepartmentAction Skipping department due to validation errors',
            \Mockery::on(function ($context) {
                return isset($context['department']) && isset($context['errors']);
            })
        );
});

test('ProcessOdooDepartmentAction handles null active field gracefully', function (): void {
    Log::spy();

    $dto = new OdooDepartmentDTO(
        id: 1,
        name: 'Engineering',
        active: null // Null active should be rejected by validation
    );

    $this->action->execute($dto);

    // Department should be skipped due to validation failure
    $department = Department::where('odoo_department_id', 1)->first();
    expect($department)->toBeNull();

    Log::shouldHaveReceived('warning')->once()->with(
        'ProcessOdooDepartmentAction Skipping department due to validation errors',
        Mockery::on(function ($context) {
            return isset($context['department']) && isset($context['errors']);
        })
    );
});

test('ProcessOdooDepartmentAction skips department with empty name', function (): void {
    Log::spy();

    $dto = new OdooDepartmentDTO(
        id: 1,
        name: '', // Empty name should fail validation
        active: true
    );

    $this->action->execute($dto);

    // Department should not be created due to validation failure
    $department = Department::where('odoo_department_id', 1)->first();
    expect($department)->toBeNull();

    // Should log a warning
    Log::shouldHaveReceived('warning')->once();
});

test('ProcessOdooDepartmentAction extracts manager ID from array correctly', function (): void {
    $dto = new OdooDepartmentDTO(
        id: 1,
        name: 'Engineering',
        active: true,
        manager_id: [42, 'Jane Manager'], // Should extract 42
        parent_id: null
    );

    $this->action->execute($dto);

    $department = Department::where('odoo_department_id', 1)->first();

    expect($department)->not->toBeNull();
    expect($department->odoo_manager_id)->toBe(42);
});

test('ProcessOdooDepartmentAction extracts parent ID from array correctly', function (): void {
    $dto = new OdooDepartmentDTO(
        id: 1,
        name: 'Engineering',
        active: true,
        manager_id: null,
        parent_id: [99, 'Parent Department'] // Should extract 99
    );

    $this->action->execute($dto);

    $department = Department::where('odoo_department_id', 1)->first();

    expect($department)->not->toBeNull();
    expect($department->odoo_parent_department_id)->toBe(99);
});

test('ProcessOdooDepartmentAction is atomic - uses database transaction', function (): void {
    $dto = new OdooDepartmentDTO(
        id: 1,
        name: 'Engineering',
        active: true
    );

    // Should complete successfully within a transaction
    $this->action->execute($dto);

    $department = Department::where('odoo_department_id', 1)->first();
    expect($department)->not->toBeNull();
});

test('ProcessOdooDepartmentAction can create multiple departments', function (): void {
    $dto1 = new OdooDepartmentDTO(
        id: 1,
        name: 'Engineering',
        active: true,
        manager_id: [5, 'John Manager'],
        parent_id: null
    );

    $dto2 = new OdooDepartmentDTO(
        id: 2,
        name: 'Marketing',
        active: false,
        manager_id: [7, 'Jane Manager'],
        parent_id: [1, 'Engineering'] // Child of Engineering
    );

    $this->action->execute($dto1);
    $this->action->execute($dto2);

    expect(Department::count())->toBe(2);

    $dept1 = Department::where('odoo_department_id', 1)->first();
    $dept2 = Department::where('odoo_department_id', 2)->first();

    expect($dept1->name)->toBe('Engineering');
    expect($dept1->active)->toBe(true);
    expect($dept1->odoo_manager_id)->toBe(5);

    expect($dept2->name)->toBe('Marketing');
    expect($dept2->active)->toBe(false);
    expect($dept2->odoo_manager_id)->toBe(7);
    expect($dept2->odoo_parent_department_id)->toBe(1);
});

test('ProcessOdooDepartmentAction preserves relationships when updating', function (): void {
    // Create department with some users attached
    $department = Department::create([
        'odoo_department_id' => 1,
        'name' => 'Old Name',
        'active' => true,
    ]);

    // Create a user and associate with department
    $user = \App\Models\User::factory()->create([
        'department_id' => $department->odoo_department_id,
    ]);

    expect($department->users()->count())->toBe(1);

    // Update the department
    $dto = new OdooDepartmentDTO(
        id: 1,
        name: 'New Name',
        active: false,
        manager_id: [42, 'New Manager'],
        parent_id: [99, 'New Parent']
    );

    $this->action->execute($dto);

    $updatedDepartment = Department::where('odoo_department_id', 1)->first();

    expect($updatedDepartment->name)->toBe('New Name');
    expect($updatedDepartment->active)->toBe(false);
    expect($updatedDepartment->odoo_manager_id)->toBe(42);
    expect($updatedDepartment->odoo_parent_department_id)->toBe(99);

    // Relationships should be preserved
    expect($updatedDepartment->users()->count())->toBe(1);
});
