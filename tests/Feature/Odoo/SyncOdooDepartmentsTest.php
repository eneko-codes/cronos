<?php

declare(strict_types=1);

use App\Clients\OdooApiClient;
use App\DataTransferObjects\Odoo\OdooDepartmentDTO;
use App\Jobs\Sync\Odoo\SyncOdooDepartments;
use App\Models\Department;
use Illuminate\Support\Facades\DB;

describe('SyncOdooDepartments job', function (): void {
    beforeEach(function (): void {
        DB::beginTransaction();
    });
    afterEach(function (): void {
        DB::rollBack();
    });

    it('creates a new department from OdooDepartmentDTO', function (): void {
        $dto = new OdooDepartmentDTO(
            id: 1,
            name: 'Engineering',
            active: true,
            manager_id: [2, 'Jane Manager'],
            parent_id: null
        );
        $mockOdoo = Mockery::mock(OdooApiClient::class);
        $mockOdoo->shouldReceive('getDepartments')->once()->andReturn(collect([$dto]));

        $job = new SyncOdooDepartments($mockOdoo);
        $job->handle();

        $department = Department::where('odoo_department_id', 1)->first();
        expect($department)->not()->toBeNull();
        expect($department->name)->toBe('Engineering');
        expect($department->active)->toBeTrue();
        expect($department->odoo_manager_employee_id)->toBe(2);
        expect($department->odoo_parent_department_id)->toBeNull();
    });

    it('updates an existing department from OdooDepartmentDTO', function (): void {
        $department = Department::factory()->create([
            'odoo_department_id' => 2,
            'name' => 'Old',
            'active' => false,
        ]);
        $dto = new OdooDepartmentDTO(
            id: 2,
            name: 'New',
            active: true,
            manager_id: [3, 'John Smith'],
            parent_id: [1, 'Parent Department']
        );
        $mockOdoo = Mockery::mock(OdooApiClient::class);
        $mockOdoo->shouldReceive('getDepartments')->once()->andReturn(collect([$dto]));

        $job = new SyncOdooDepartments($mockOdoo);
        $job->handle();

        $department->refresh();
        expect($department->name)->toBe('New');
        expect($department->active)->toBeTrue();
        expect($department->odoo_manager_employee_id)->toBe(3);
        expect($department->odoo_parent_department_id)->toBe(1);
    });
});
