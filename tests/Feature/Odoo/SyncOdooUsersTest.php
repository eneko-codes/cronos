<?php

declare(strict_types=1);

use App\Clients\OdooApiClient;
use App\DataTransferObjects\Odoo\OdooUserDTO;
use App\Jobs\Sync\Odoo\SyncOdooUsers;
use App\Models\User;
use Illuminate\Support\Facades\DB;

describe('SyncOdooUsers job', function (): void {
    beforeEach(function (): void {
        // Use in-memory DB for isolation
        DB::beginTransaction();
    });
    afterEach(function (): void {
        DB::rollBack();
    });

    it('creates a new user from OdooUserDTO', function (): void {
        $dto = new OdooUserDTO(
            id: 123,
            work_email: 'odoo.user@example.com',
            name: 'Odoo User',
            tz: 'Europe/Madrid',
            active: true,
            department_id: null,
            category_ids: [],
            resource_calendar_id: null,
            job_title: 'Developer',
            parent_id: null
        );
        $mockOdoo = Mockery::mock(OdooApiClient::class);
        $mockOdoo->shouldReceive('getUsers')->once()->andReturn(collect([$dto]));

        // Run the job
        $job = new SyncOdooUsers($mockOdoo);
        $job->handle();

        $user = User::where('odoo_id', 123)->first();
        expect($user)->not()->toBeNull();
        expect($user->name)->toBe('Odoo User');
        expect($user->email)->toBe('odoo.user@example.com');
        expect($user->timezone)->toBe('Europe/Madrid');
        expect($user->is_active)->toBeTrue();
        expect($user->job_title)->toBe('Developer');
    });

    it('updates an existing user from OdooUserDTO', function (): void {
        $user = User::factory()->create([
            'odoo_id' => 456,
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'is_active' => false,
        ]);
        $dto = new OdooUserDTO(
            id: 456,
            work_email: 'new@example.com',
            name: 'New Name',
            tz: 'UTC',
            active: true,
            department_id: null,
            category_ids: [],
            resource_calendar_id: null,
            job_title: 'Lead',
            parent_id: null
        );
        $mockOdoo = Mockery::mock(OdooApiClient::class);
        $mockOdoo->shouldReceive('getUsers')->once()->andReturn(collect([$dto]));

        $job = new SyncOdooUsers($mockOdoo);
        $job->handle();

        $user->refresh();
        expect($user->name)->toBe('New Name');
        expect($user->email)->toBe('new@example.com');
        expect($user->is_active)->toBeTrue();
        expect($user->job_title)->toBe('Lead');
    });

    it('deactivates users not present in Odoo', function (): void {
        $user = User::factory()->create([
            'odoo_id' => 789,
            'is_active' => true,
        ]);
        $dto = new OdooUserDTO(
            id: 999,
            work_email: 'other@example.com',
            name: 'Other',
            tz: 'UTC',
            active: true,
            department_id: null,
            category_ids: [],
            resource_calendar_id: null,
            job_title: null,
            parent_id: null
        );
        $mockOdoo = Mockery::mock(OdooApiClient::class);
        $mockOdoo->shouldReceive('getUsers')->once()->andReturn(collect([$dto]));

        $job = new SyncOdooUsers($mockOdoo);
        $job->handle();

        $user->refresh();
        expect($user->is_active)->toBeFalse();
    });
});
