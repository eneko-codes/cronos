<?php

declare(strict_types=1);

use App\Clients\OdooApiClient;
use App\DataTransferObjects\Odoo\OdooLeaveTypeDTO;
use App\Jobs\Sync\Odoo\SyncOdooLeaveTypes;
use App\Models\LeaveType;
use Illuminate\Support\Facades\DB;

describe('SyncOdooLeaveTypes job', function (): void {
    beforeEach(function (): void {
        DB::beginTransaction();
    });
    afterEach(function (): void {
        DB::rollBack();
    });

    it('creates a new leave type from OdooLeaveTypeDTO', function (): void {
        $dto = new OdooLeaveTypeDTO(
            id: 1,
            name: 'Annual',
            active: true,
            allocation_type: 'fixed',
            validation_type: 'manager',
            request_unit: 'day',
            unpaid: false
        );
        $mockOdoo = Mockery::mock(OdooApiClient::class);
        $mockOdoo->shouldReceive('getLeaveTypes')->once()->andReturn(collect([$dto]));

        $job = new SyncOdooLeaveTypes($mockOdoo);
        $job->handle();

        $leaveType = LeaveType::where('odoo_leave_type_id', 1)->first();
        expect($leaveType)->not()->toBeNull();
        expect($leaveType->name)->toBe('Annual');
        expect($leaveType->active)->toBeTrue();
        expect($leaveType->validation_type)->toBe('manager');
        expect($leaveType->request_unit)->toBe('day');
        expect($leaveType->is_unpaid)->toBeFalse();
    });

    it('updates an existing leave type from OdooLeaveTypeDTO', function (): void {
        $leaveType = LeaveType::factory()->create([
            'odoo_leave_type_id' => 2,
            'name' => 'Old',
            'active' => false,
        ]);
        $dto = new OdooLeaveTypeDTO(
            id: 2,
            name: 'New',
            active: true,
            allocation_type: 'no',
            validation_type: 'hr',
            request_unit: 'hour',
            unpaid: true
        );
        $mockOdoo = Mockery::mock(OdooApiClient::class);
        $mockOdoo->shouldReceive('getLeaveTypes')->once()->andReturn(collect([$dto]));

        $job = new SyncOdooLeaveTypes($mockOdoo);
        $job->handle();

        $leaveType->refresh();
        expect($leaveType->name)->toBe('New');
        expect($leaveType->active)->toBeTrue();
        expect($leaveType->validation_type)->toBe('hr');
        expect($leaveType->request_unit)->toBe('hour');
        expect($leaveType->is_unpaid)->toBeTrue();
    });
});
