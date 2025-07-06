<?php

declare(strict_types=1);

use App\Clients\OdooApiClient;
use App\DataTransferObjects\Odoo\OdooLeaveDTO;
use App\Jobs\Sync\Odoo\SyncOdooLeaves;
use App\Models\LeaveType;
use App\Models\UserLeave;
use Illuminate\Support\Facades\DB;

describe('SyncOdooLeaves job', function (): void {
    beforeEach(function (): void {
        DB::beginTransaction();
    });
    afterEach(function (): void {
        DB::rollBack();
    });

    it('creates a new leave from OdooLeaveDTO', function (): void {
        LeaveType::factory()->create(['odoo_leave_type_id' => 1]);
        $dto = new OdooLeaveDTO(
            id: 1,
            holiday_type: 'employee',
            date_from: '2024-01-01 09:00:00',
            date_to: '2024-01-01 18:00:00',
            number_of_days: 1.0,
            state: 'validate',
            holiday_status_id: 1,
            request_hour_from: 9.0,
            request_hour_to: 18.0,
            employee_id: null,
            category_id: null,
            department_id: null
        );
        $mockOdoo = Mockery::mock(OdooApiClient::class);
        $mockOdoo->shouldReceive('getLeaves')->once()->andReturn(collect([$dto]));

        $job = new SyncOdooLeaves($mockOdoo);
        $job->handle();

        $leave = UserLeave::where('odoo_leave_id', 1)->first();
        expect($leave)->not()->toBeNull();
        expect($leave->type)->toBe('employee');
        expect($leave->status)->toBe('validate');
        expect($leave->duration_days)->toBe(1.0);
        expect($leave->leave_type_id)->toBe(1);
    });

    it('updates an existing leave from OdooLeaveDTO', function (): void {
        LeaveType::factory()->create(['odoo_leave_type_id' => 2]);
        $leave = UserLeave::factory()->create([
            'odoo_leave_id' => 2,
            'type' => 'employee',
            'status' => 'draft',
            'leave_type_id' => 2,
        ]);
        $dto = new OdooLeaveDTO(
            id: 2,
            holiday_type: 'employee',
            date_from: '2024-01-02 09:00:00',
            date_to: '2024-01-02 18:00:00',
            number_of_days: 0.5,
            state: 'validate',
            holiday_status_id: 2,
            request_hour_from: 9.0,
            request_hour_to: 13.0,
            employee_id: null,
            category_id: null,
            department_id: null
        );
        $mockOdoo = Mockery::mock(OdooApiClient::class);
        $mockOdoo->shouldReceive('getLeaves')->once()->andReturn(collect([$dto]));

        $job = new SyncOdooLeaves($mockOdoo);
        $job->handle();

        $leave->refresh();
        expect($leave->status)->toBe('validate');
        expect($leave->duration_days)->toBe(0.5);
    });
});
