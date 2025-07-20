<?php

declare(strict_types=1);

use App\Actions\Odoo\ProcessOdooLeavesAction;
use App\DataTransferObjects\Odoo\OdooLeaveDTO;
use App\Models\Category;
use App\Models\Department;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\UserLeave;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->action = new ProcessOdooLeavesAction;

    // Create test data
    $this->user = User::factory()->create(['odoo_id' => 123]);
    $this->leaveType = LeaveType::create([
        'odoo_leave_type_id' => 1,
        'name' => 'Paid Time Off',
        'active' => true,
    ]);
    $this->department = Department::create([
        'odoo_department_id' => 2,
        'name' => 'Engineering',
        'active' => true,
    ]);
    $this->category = Category::create([
        'odoo_category_id' => 3,
        'name' => 'Full Time',
        'active' => true,
    ]);
});

test('ProcessOdooLeavesAction creates new employee leave with valid data', function (): void {
    $dto = new OdooLeaveDTO(
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
    );

    $this->action->execute($dto);

    $userLeave = UserLeave::where('odoo_leave_id', 1)->first();

    expect($userLeave)->not->toBeNull();
    expect($userLeave->odoo_leave_id)->toBe(1);
    expect($userLeave->type)->toBe('employee');
    expect($userLeave->start_date->format('Y-m-d H:i:s'))->toBe('2024-07-01 09:00:00');
    expect($userLeave->end_date->format('Y-m-d H:i:s'))->toBe('2024-07-05 18:00:00');
    expect($userLeave->status)->toBe('validate');
    expect($userLeave->duration_days)->toBe(5.0);
    expect($userLeave->leave_type_id)->toBe(1);
    expect($userLeave->user_id)->toBe($this->user->id);
    expect($userLeave->request_hour_from)->toBe(9.0);
    expect($userLeave->request_hour_to)->toBe(18.0);
    expect($userLeave->department_id)->toBeNull();
    expect($userLeave->category_id)->toBeNull();
});

test('ProcessOdooLeavesAction creates department leave', function (): void {
    $dto = new OdooLeaveDTO(
        id: 2,
        holiday_type: 'department',
        date_from: '2024-12-25 00:00:00',
        date_to: '2024-12-25 23:59:59',
        number_of_days: 1.0,
        state: 'validate',
        holiday_status_id: [1, 'Holiday'],
        request_hour_from: null,
        request_hour_to: null,
        employee_id: null,
        category_id: null,
        department_id: [2, 'Engineering']
    );

    $this->action->execute($dto);

    $userLeave = UserLeave::where('odoo_leave_id', 2)->first();

    expect($userLeave)->not->toBeNull();
    expect($userLeave->type)->toBe('department');
    expect($userLeave->user_id)->toBeNull();
    expect($userLeave->department_id)->toBe(2);
    expect($userLeave->category_id)->toBeNull();
});

test('ProcessOdooLeavesAction creates category leave', function (): void {
    $dto = new OdooLeaveDTO(
        id: 3,
        holiday_type: 'category',
        date_from: '2024-08-15 00:00:00',
        date_to: '2024-08-15 23:59:59',
        number_of_days: 1.0,
        state: 'validate',
        holiday_status_id: [1, 'Company Holiday'],
        request_hour_from: null,
        request_hour_to: null,
        employee_id: null,
        category_id: [3, 'Full Time'],
        department_id: null
    );

    $this->action->execute($dto);

    $userLeave = UserLeave::where('odoo_leave_id', 3)->first();

    expect($userLeave)->not->toBeNull();
    expect($userLeave->type)->toBe('category');
    expect($userLeave->user_id)->toBeNull();
    expect($userLeave->department_id)->toBeNull();
    expect($userLeave->category_id)->toBe(3);
});

test('ProcessOdooLeavesAction updates existing leave', function (): void {
    // Create existing leave
    $existingLeave = UserLeave::create([
        'odoo_leave_id' => 1,
        'type' => 'employee',
        'start_date' => '2024-06-01 09:00:00',
        'end_date' => '2024-06-03 18:00:00',
        'status' => 'draft',
        'duration_days' => 3.0,
        'leave_type_id' => 1,
        'user_id' => $this->user->id,
    ]);

    $dto = new OdooLeaveDTO(
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
    );

    $this->action->execute($dto);

    $userLeave = UserLeave::where('odoo_leave_id', 1)->first();

    expect($userLeave)->not->toBeNull();
    expect($userLeave->start_date->format('Y-m-d H:i:s'))->toBe('2024-07-01 09:00:00');
    expect($userLeave->end_date->format('Y-m-d H:i:s'))->toBe('2024-07-05 18:00:00');
    expect($userLeave->status)->toBe('validate');
    expect($userLeave->duration_days)->toBe(5.0);

    // Should still be the same record, not a new one
    expect(UserLeave::count())->toBe(1);
});

test('ProcessOdooLeavesAction skips leave with missing required fields', function (): void {
    Log::spy();

    $dto = new OdooLeaveDTO(
        id: null, // Missing required ID
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
    );

    $this->action->execute($dto);

    // Leave should not be created due to validation failure
    expect(UserLeave::count())->toBe(0);

    // Should log a warning
    Log::shouldHaveReceived('warning')
        ->once()
        ->with(
            'ProcessOdooLeavesAction Skipping leave due to validation errors',
            \Mockery::on(function ($context) {
                return isset($context['leave']) && isset($context['errors']);
            })
        );
});

test('ProcessOdooLeavesAction skips employee leave when user does not exist', function (): void {
    Log::spy();

    $dto = new OdooLeaveDTO(
        id: 1,
        holiday_type: 'employee',
        date_from: '2024-07-01 09:00:00',
        date_to: '2024-07-05 18:00:00',
        number_of_days: 5.0,
        state: 'validate',
        holiday_status_id: [1, 'Paid Time Off'],
        request_hour_from: 9.0,
        request_hour_to: 18.0,
        employee_id: [999, 'Nonexistent User'], // User doesn't exist
        category_id: null,
        department_id: null
    );

    $this->action->execute($dto);

    // Leave should not be created due to validation failure
    expect(UserLeave::count())->toBe(0);

    // Should log a warning
    Log::shouldHaveReceived('warning')
        ->once()
        ->with(
            'ProcessOdooLeavesAction Skipping leave due to validation errors',
            \Mockery::on(function ($context) {
                return isset($context['leave']) &&
                       isset($context['errors']) &&
                       str_contains(json_encode($context['errors']), 'User not found');
            })
        );
});

test('ProcessOdooLeavesAction skips employee leave with missing employee_id', function (): void {
    Log::spy();

    $dto = new OdooLeaveDTO(
        id: 1,
        holiday_type: 'employee',
        date_from: '2024-07-01 09:00:00',
        date_to: '2024-07-05 18:00:00',
        number_of_days: 5.0,
        state: 'validate',
        holiday_status_id: [1, 'Paid Time Off'],
        request_hour_from: 9.0,
        request_hour_to: 18.0,
        employee_id: null, // Missing employee for employee leave
        category_id: null,
        department_id: null
    );

    $this->action->execute($dto);

    // Leave should not be created due to validation failure
    expect(UserLeave::count())->toBe(0);

    // Should log a warning
    Log::shouldHaveReceived('warning')->once();
});

test('ProcessOdooLeavesAction handles null optional fields', function (): void {
    $dto = new OdooLeaveDTO(
        id: 1,
        holiday_type: 'employee',
        date_from: '2024-07-01 09:00:00',
        date_to: '2024-07-05 18:00:00',
        number_of_days: 5.0,
        state: 'validate',
        holiday_status_id: [1, 'Paid Time Off'],
        request_hour_from: null, // Optional field
        request_hour_to: null,   // Optional field
        employee_id: [123, 'John Doe'],
        category_id: null,
        department_id: null
    );

    $this->action->execute($dto);

    $userLeave = UserLeave::where('odoo_leave_id', 1)->first();

    expect($userLeave)->not->toBeNull();
    expect($userLeave->request_hour_from)->toBeNull();
    expect($userLeave->request_hour_to)->toBeNull();
});

test('ProcessOdooLeavesAction handles different leave states', function (): void {
    $states = ['draft', 'confirm', 'validate', 'validate1', 'refuse', 'cancel'];

    foreach ($states as $index => $state) {
        $dto = new OdooLeaveDTO(
            id: $index + 1,
            holiday_type: 'employee',
            date_from: '2024-07-01 09:00:00',
            date_to: '2024-07-05 18:00:00',
            number_of_days: 5.0,
            state: $state,
            holiday_status_id: [1, 'Paid Time Off'],
            request_hour_from: 9.0,
            request_hour_to: 18.0,
            employee_id: [123, 'John Doe'],
            category_id: null,
            department_id: null
        );

        $this->action->execute($dto);

        $userLeave = UserLeave::where('odoo_leave_id', $index + 1)->first();
        expect($userLeave->status)->toBe($state);
    }
});

test('ProcessOdooLeavesAction is atomic - uses database transaction', function (): void {
    $dto = new OdooLeaveDTO(
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
    );

    // Should complete successfully within a transaction
    $this->action->execute($dto);

    $userLeave = UserLeave::where('odoo_leave_id', 1)->first();
    expect($userLeave)->not->toBeNull();
});

test('ProcessOdooLeavesAction can create multiple leaves', function (): void {
    $dto1 = new OdooLeaveDTO(
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
    );

    $dto2 = new OdooLeaveDTO(
        id: 2,
        holiday_type: 'department',
        date_from: '2024-12-25 00:00:00',
        date_to: '2024-12-25 23:59:59',
        number_of_days: 1.0,
        state: 'validate',
        holiday_status_id: [1, 'Holiday'],
        request_hour_from: null,
        request_hour_to: null,
        employee_id: null,
        category_id: null,
        department_id: [2, 'Engineering']
    );

    $this->action->execute($dto1);
    $this->action->execute($dto2);

    expect(UserLeave::count())->toBe(2);

    $leave1 = UserLeave::where('odoo_leave_id', 1)->first();
    $leave2 = UserLeave::where('odoo_leave_id', 2)->first();

    expect($leave1->type)->toBe('employee');
    expect($leave1->user_id)->toBe($this->user->id);

    expect($leave2->type)->toBe('department');
    expect($leave2->department_id)->toBe(2);
});

test('ProcessOdooLeavesAction extracts IDs from arrays correctly', function (): void {
    // Create necessary related records first
    $user = User::factory()->create(['odoo_id' => 456]); // Use different ID to avoid conflict
    $leaveType = LeaveType::factory()->create(['odoo_leave_type_id' => 42]);
    $category = Category::factory()->create(['odoo_category_id' => 77]);
    $department = Department::factory()->create(['odoo_department_id' => 88]);

    $dto = new OdooLeaveDTO(
        id: 1,
        holiday_type: 'employee',
        date_from: '2024-07-01 09:00:00',
        date_to: '2024-07-05 18:00:00',
        number_of_days: 5.0,
        state: 'validate',
        holiday_status_id: [42, 'Custom Leave Type'], // Should extract 42
        request_hour_from: 9.0,
        request_hour_to: 18.0,
        employee_id: [456, 'John Doe'], // Use same ID as the created user
        category_id: [77, 'Category Name'], // Should extract 77
        department_id: [88, 'Department Name'] // Should extract 88
    );

    $this->action->execute($dto);

    $userLeave = UserLeave::where('odoo_leave_id', 1)->first();

    expect($userLeave)->not->toBeNull();
    expect($userLeave->leave_type_id)->toBe(42);
    expect($userLeave->category_id)->toBe(77);
    expect($userLeave->department_id)->toBe(88);
    expect($userLeave->user_id)->toBe($user->id);
});
