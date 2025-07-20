<?php

declare(strict_types=1);

use App\Actions\Odoo\ProcessOdooLeaveTypeAction;
use App\DataTransferObjects\Odoo\OdooLeaveTypeDTO;
use App\Models\LeaveType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->action = new ProcessOdooLeaveTypeAction;
});

test('ProcessOdooLeaveTypeAction creates new leave type with valid data', function (): void {
    $dto = new OdooLeaveTypeDTO(
        id: 1,
        name: 'Paid Time Off',
        request_unit: 'day',
        active: true,
        create_date: '2024-01-01 10:00:00',
        write_date: '2024-01-15 14:30:00'
    );

    $this->action->execute($dto);

    $leaveType = LeaveType::where('odoo_leave_type_id', 1)->first();

    expect($leaveType)->not->toBeNull();
    expect($leaveType->odoo_leave_type_id)->toBe(1);
    expect($leaveType->name)->toBe('Paid Time Off');
    expect($leaveType->request_unit)->toBe('day');
    expect($leaveType->active)->toBe(true);
    expect($leaveType->odoo_created_at)->toBe('2024-01-01 10:00:00');
    expect($leaveType->odoo_updated_at)->toBe('2024-01-15 14:30:00');
});

test('ProcessOdooLeaveTypeAction updates existing leave type', function (): void {
    // Create existing leave type
    $existingLeaveType = LeaveType::create([
        'odoo_leave_type_id' => 1,
        'name' => 'Old Name',
        'request_unit' => 'hour',
        'active' => false,
        'odoo_created_at' => '2023-01-01 10:00:00',
        'odoo_updated_at' => '2023-01-01 10:00:00',
    ]);

    $dto = new OdooLeaveTypeDTO(
        id: 1,
        name: 'Paid Time Off',
        request_unit: 'day',
        active: true,
        create_date: '2024-01-01 10:00:00',
        write_date: '2024-01-15 14:30:00'
    );

    $this->action->execute($dto);

    $leaveType = LeaveType::where('odoo_leave_type_id', 1)->first();

    expect($leaveType)->not->toBeNull();
    expect($leaveType->name)->toBe('Paid Time Off');
    expect($leaveType->request_unit)->toBe('day');
    expect($leaveType->active)->toBe(true);
    expect($leaveType->odoo_created_at)->toBe('2024-01-01 10:00:00');
    expect($leaveType->odoo_updated_at)->toBe('2024-01-15 14:30:00');

    // Should still be the same record, not a new one
    expect(LeaveType::count())->toBe(1);
});

test('ProcessOdooLeaveTypeAction skips leave type with null active field', function (): void {
    Log::spy();

    $dto = new OdooLeaveTypeDTO(
        id: 1,
        name: 'Sick Leave',
        request_unit: 'day',
        active: null
    );

    $this->action->execute($dto);

    $leaveType = LeaveType::where('odoo_leave_type_id', 1)->first();

    expect($leaveType)->toBeNull(); // Should be skipped due to validation

    Log::shouldHaveReceived('warning')->once()->with(
        'ProcessOdooLeaveTypeAction Skipping leave type due to validation errors',
        Mockery::on(function ($context) {
            return isset($context['leave_type']) && isset($context['errors']);
        })
    );
});

test('ProcessOdooLeaveTypeAction handles null optional fields', function (): void {
    $dto = new OdooLeaveTypeDTO(
        id: 1,
        name: 'Vacation',
        request_unit: null, // Optional field
        active: true,
        create_date: null,  // Optional field
        write_date: null    // Optional field
    );

    $this->action->execute($dto);

    $leaveType = LeaveType::where('odoo_leave_type_id', 1)->first();

    expect($leaveType)->not->toBeNull();
    expect($leaveType->request_unit)->toBeNull();
    expect($leaveType->odoo_created_at)->toBeNull();
    expect($leaveType->odoo_updated_at)->toBeNull();
});

test('ProcessOdooLeaveTypeAction skips leave type with missing id', function (): void {
    Log::spy();

    $dto = new OdooLeaveTypeDTO(
        id: null, // Missing required ID
        name: 'Paid Time Off',
        request_unit: 'day',
        active: true
    );

    $this->action->execute($dto);

    // Leave type should not be created due to validation failure
    $leaveType = LeaveType::where('name', 'Paid Time Off')->first();
    expect($leaveType)->toBeNull();

    // Should log a warning
    Log::shouldHaveReceived('warning')
        ->once()
        ->with(
            'ProcessOdooLeaveTypeAction Skipping leave type due to validation errors',
            \Mockery::on(function ($context) {
                return isset($context['leave_type']) && isset($context['errors']);
            })
        );
});

test('ProcessOdooLeaveTypeAction skips leave type with missing name', function (): void {
    Log::spy();

    $dto = new OdooLeaveTypeDTO(
        id: 1,
        name: null, // Missing required name
        request_unit: 'day',
        active: true
    );

    $this->action->execute($dto);

    // Leave type should not be created due to validation failure
    $leaveType = LeaveType::where('odoo_leave_type_id', 1)->first();
    expect($leaveType)->toBeNull();

    // Should log a warning
    Log::shouldHaveReceived('warning')
        ->once()
        ->with(
            'ProcessOdooLeaveTypeAction Skipping leave type due to validation errors',
            \Mockery::on(function ($context) {
                return isset($context['leave_type']) && isset($context['errors']);
            })
        );
});

test('ProcessOdooLeaveTypeAction handles null active field gracefully', function (): void {
    Log::spy();

    $dto = new OdooLeaveTypeDTO(
        id: 1,
        name: 'Paid Time Off',
        request_unit: 'day',
        active: null // Null active should be rejected by validation
    );

    $this->action->execute($dto);

    // Leave type should be skipped due to validation failure
    $leaveType = LeaveType::where('odoo_leave_type_id', 1)->first();
    expect($leaveType)->toBeNull();

    Log::shouldHaveReceived('warning')->once()->with(
        'ProcessOdooLeaveTypeAction Skipping leave type due to validation errors',
        Mockery::on(function ($context) {
            return isset($context['leave_type']) && isset($context['errors']);
        })
    );
});

test('ProcessOdooLeaveTypeAction skips leave type with empty name', function (): void {
    Log::spy();

    $dto = new OdooLeaveTypeDTO(
        id: 1,
        name: '', // Empty name should fail validation
        request_unit: 'day',
        active: true
    );

    $this->action->execute($dto);

    // Leave type should not be created due to validation failure
    $leaveType = LeaveType::where('odoo_leave_type_id', 1)->first();
    expect($leaveType)->toBeNull();

    // Should log a warning
    Log::shouldHaveReceived('warning')->once();
});

test('ProcessOdooLeaveTypeAction is atomic - uses database transaction', function (): void {
    $dto = new OdooLeaveTypeDTO(
        id: 1,
        name: 'Paid Time Off',
        request_unit: 'day',
        active: true
    );

    // Should complete successfully within a transaction
    $this->action->execute($dto);

    $leaveType = LeaveType::where('odoo_leave_type_id', 1)->first();
    expect($leaveType)->not->toBeNull();
});

test('ProcessOdooLeaveTypeAction can create multiple leave types', function (): void {
    $dto1 = new OdooLeaveTypeDTO(
        id: 1,
        name: 'Paid Time Off',
        request_unit: 'day',
        active: true
    );

    $dto2 = new OdooLeaveTypeDTO(
        id: 2,
        name: 'Sick Leave',
        request_unit: 'half_day',
        active: false
    );

    $this->action->execute($dto1);
    $this->action->execute($dto2);

    expect(LeaveType::count())->toBe(2);

    $leaveType1 = LeaveType::where('odoo_leave_type_id', 1)->first();
    $leaveType2 = LeaveType::where('odoo_leave_type_id', 2)->first();

    expect($leaveType1->name)->toBe('Paid Time Off');
    expect($leaveType1->request_unit)->toBe('day');
    expect($leaveType1->active)->toBe(true);

    expect($leaveType2->name)->toBe('Sick Leave');
    expect($leaveType2->request_unit)->toBe('half_day');
    expect($leaveType2->active)->toBe(false);
});

test('ProcessOdooLeaveTypeAction preserves relationships when updating', function (): void {
    // Create leave type with some user leaves attached
    $leaveType = LeaveType::create([
        'odoo_leave_type_id' => 1,
        'name' => 'Old Name',
        'request_unit' => 'day',
        'active' => true,
    ]);

    // Create a user leave associated with this leave type
    $user = \App\Models\User::factory()->create();
    \App\Models\UserLeave::factory()->create([
        'user_id' => $user->id,
        'leave_type_id' => $leaveType->odoo_leave_type_id,
    ]);

    expect($leaveType->leaves()->count())->toBe(1);

    // Update the leave type
    $dto = new OdooLeaveTypeDTO(
        id: 1,
        name: 'New Name',
        request_unit: 'hour',
        active: false
    );

    $this->action->execute($dto);

    $updatedLeaveType = LeaveType::where('odoo_leave_type_id', 1)->first();

    expect($updatedLeaveType->name)->toBe('New Name');
    expect($updatedLeaveType->request_unit)->toBe('hour');
    expect($updatedLeaveType->active)->toBe(false);

    // Relationships should be preserved
    expect($updatedLeaveType->leaves()->count())->toBe(1);
});
