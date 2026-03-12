<?php

declare(strict_types=1);

use App\Clients\OdooApiClient;
use App\Clients\ProofhubApiClient;
use App\Clients\SystemPinApiClient;
use App\DataTransferObjects\Odoo\OdooCategoryDTO;
use App\DataTransferObjects\Odoo\OdooDepartmentDTO;
use App\DataTransferObjects\Odoo\OdooLeaveDTO;
use App\DataTransferObjects\Odoo\OdooLeaveTypeDTO;
use App\DataTransferObjects\Odoo\OdooScheduleDetailDTO;
use App\DataTransferObjects\Odoo\OdooScheduleDTO;
use App\DataTransferObjects\Odoo\OdooUserDTO;
use App\DataTransferObjects\Proofhub\ProofhubProjectDTO;
use App\DataTransferObjects\Proofhub\ProofhubTaskDTO;
use App\DataTransferObjects\Proofhub\ProofhubTimeEntryDTO;
use App\DataTransferObjects\Proofhub\ProofhubUserDTO;
use App\DataTransferObjects\SystemPin\SystemPinUserDTO;
use App\Enums\Platform;
use App\Jobs\Sync\Odoo\SyncOdooCategoriesJob;
use App\Jobs\Sync\Odoo\SyncOdooDepartmentsJob;
use App\Jobs\Sync\Odoo\SyncOdooLeavesJob;
use App\Jobs\Sync\Odoo\SyncOdooLeaveTypesJob;
use App\Jobs\Sync\Odoo\SyncOdooScheduleDetailsJob;
use App\Jobs\Sync\Odoo\SyncOdooSchedulesJob;
use App\Jobs\Sync\Odoo\SyncOdooUsersJob;
use App\Jobs\Sync\Proofhub\SyncProofhubProjectsJob;
use App\Jobs\Sync\Proofhub\SyncProofhubTasksJob;
use App\Jobs\Sync\Proofhub\SyncProofhubTimeEntriesJob;
use App\Jobs\Sync\Proofhub\SyncProofhubUsersJob;
use App\Jobs\Sync\SystemPin\SyncSystemPinUsersJob;
use App\Models\Category;
use App\Models\Department;
use App\Models\LeaveType;
use App\Models\Project;
use App\Models\Schedule;
use App\Models\ScheduleDetail;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\UserExternalIdentity;
use App\Models\UserLeave;
use Illuminate\Database\Eloquent\Model;

beforeEach(function (): void {
    Model::unguard();
});

afterEach(function (): void {
    Model::reguard();
});

/*
|--------------------------------------------------------------------------
| Odoo Sync Jobs Cleanup Tests
|--------------------------------------------------------------------------
*/

describe('SyncOdooUsersJob cleanup', function (): void {
    it('deactivates users not present in Odoo API response', function (): void {
        // Create users with Odoo identity
        $activeUser = User::create([
            'user_type' => RoleType::User,
            'name' => 'Active User',
            'email' => 'active@test.com',
            'is_active' => true,
        ]);
        UserExternalIdentity::create([
            'user_id' => $activeUser->id,
            'platform' => Platform::Odoo,
            'external_id' => '100',
        ]);

        $removedUser = User::create([
            'user_type' => RoleType::User,
            'name' => 'Removed User',
            'email' => 'removed@test.com',
            'is_active' => true,
        ]);
        UserExternalIdentity::create([
            'user_id' => $removedUser->id,
            'platform' => Platform::Odoo,
            'external_id' => '200',
        ]);

        // Mock API to only return the first user
        $mockOdoo = mock(OdooApiClient::class);
        $mockOdoo->shouldReceive('getUsers')
            ->once()
            ->andReturn(collect([
                new OdooUserDTO(
                    id: 100,
                    work_email: 'active@test.com',
                    name: 'Active User',
                    active: true,
                ),
            ]));

        // Run job
        $job = new SyncOdooUsersJob($mockOdoo);
        $job->handle();

        // Assert: removed user should be deactivated
        expect(User::find($activeUser->id)->is_active)->toBeTrue();
        expect(User::find($removedUser->id)->is_active)->toBeFalse();
    });

    it('does not deactivate users without Odoo identity', function (): void {
        // Create user without Odoo identity
        $localUser = User::create([
            'user_type' => RoleType::User,
            'name' => 'Local User',
            'email' => 'local@test.com',
            'is_active' => true,
        ]);

        // Mock API to return empty
        $mockOdoo = mock(OdooApiClient::class);
        $mockOdoo->shouldReceive('getUsers')
            ->once()
            ->andReturn(collect([]));

        $job = new SyncOdooUsersJob($mockOdoo);
        $job->handle();

        // Local user should still be active
        expect(User::find($localUser->id)->is_active)->toBeTrue();
    });
});

describe('SyncOdooDepartmentsJob cleanup', function (): void {
    it('deactivates departments not present in Odoo API response', function (): void {
        // Create departments
        Department::create([
            'odoo_department_id' => 1,
            'name' => 'Engineering',
            'active' => true,
        ]);
        Department::create([
            'odoo_department_id' => 2,
            'name' => 'Removed Dept',
            'active' => true,
        ]);

        // Mock API to only return department 1
        $mockOdoo = mock(OdooApiClient::class);
        $mockOdoo->shouldReceive('getDepartments')
            ->once()
            ->andReturn(collect([
                new OdooDepartmentDTO(id: 1, name: 'Engineering'),
            ]));

        $job = new SyncOdooDepartmentsJob($mockOdoo);
        $job->handle();

        expect(Department::find(1)->active)->toBeTrue();
        expect(Department::find(2)->active)->toBeFalse();
    });
});

describe('SyncOdooCategoriesJob cleanup', function (): void {
    it('deactivates categories not present in Odoo API response', function (): void {
        Category::create(['odoo_category_id' => 1, 'name' => 'Active Category', 'active' => true]);
        Category::create(['odoo_category_id' => 2, 'name' => 'Removed Category', 'active' => true]);

        $mockOdoo = mock(OdooApiClient::class);
        $mockOdoo->shouldReceive('getCategories')
            ->once()
            ->andReturn(collect([
                new OdooCategoryDTO(id: 1, name: 'Active Category'),
            ]));

        $job = new SyncOdooCategoriesJob($mockOdoo);
        $job->handle();

        expect(Category::find(1)->active)->toBeTrue();
        expect(Category::find(2)->active)->toBeFalse();
    });
});

describe('SyncOdooSchedulesJob cleanup', function (): void {
    it('deactivates schedules not present in Odoo API response', function (): void {
        Schedule::create(['odoo_schedule_id' => 1, 'description' => 'Active Schedule', 'active' => true]);
        Schedule::create(['odoo_schedule_id' => 2, 'description' => 'Removed Schedule', 'active' => true]);

        $mockOdoo = mock(OdooApiClient::class);
        $mockOdoo->shouldReceive('getSchedules')
            ->once()
            ->andReturn(collect([
                new OdooScheduleDTO(id: 1, name: 'Active Schedule'),
            ]));

        $job = new SyncOdooSchedulesJob($mockOdoo);
        $job->handle();

        expect(Schedule::find(1)->active)->toBeTrue();
        expect(Schedule::find(2)->active)->toBeFalse();
    });
});

describe('SyncOdooLeaveTypesJob cleanup', function (): void {
    it('deactivates leave types not present in Odoo API response', function (): void {
        LeaveType::create(['odoo_leave_type_id' => 1, 'name' => 'Vacation', 'active' => true]);
        LeaveType::create(['odoo_leave_type_id' => 2, 'name' => 'Removed Type', 'active' => true]);

        $mockOdoo = mock(OdooApiClient::class);
        $mockOdoo->shouldReceive('getLeaveTypes')
            ->once()
            ->andReturn(collect([
                new OdooLeaveTypeDTO(id: 1, name: 'Vacation'),
            ]));

        $job = new SyncOdooLeaveTypesJob($mockOdoo);
        $job->handle();

        expect(LeaveType::find(1)->active)->toBeTrue();
        expect(LeaveType::find(2)->active)->toBeFalse();
    });
});

describe('SyncOdooScheduleDetailsJob cleanup', function (): void {
    it('deactivates schedule details not present in Odoo API response', function (): void {
        // Create parent schedule first
        Schedule::create(['odoo_schedule_id' => 1, 'description' => 'Schedule', 'active' => true]);

        ScheduleDetail::create([
            'odoo_detail_id' => 1,
            'odoo_schedule_id' => 1,
            'weekday' => 0,
            'start' => '2024-01-01 09:00:00',
            'end' => '2024-01-01 17:00:00',
            'active' => true,
        ]);
        ScheduleDetail::create([
            'odoo_detail_id' => 2,
            'odoo_schedule_id' => 1,
            'weekday' => 1,
            'start' => '2024-01-01 09:00:00',
            'end' => '2024-01-01 17:00:00',
            'active' => true,
        ]);

        $mockOdoo = mock(OdooApiClient::class);
        $mockOdoo->shouldReceive('getScheduleDetails')
            ->once()
            ->andReturn(collect([
                new OdooScheduleDetailDTO(id: 1, calendar_id: [1, 'Schedule'], dayofweek: '0', hour_from: 9.0, hour_to: 17.0),
            ]));

        $job = new SyncOdooScheduleDetailsJob($mockOdoo);
        $job->handle();

        expect(ScheduleDetail::where('odoo_detail_id', 1)->first()->active)->toBeTrue();
        expect(ScheduleDetail::where('odoo_detail_id', 2)->first()->active)->toBeFalse();
    });
});

describe('SyncOdooLeavesJob cleanup', function (): void {
    it('deletes leaves not present in Odoo API response within date range', function (): void {
        $user = User::create(['name' => 'Test User', 'email' => 'test@test.com']);
        UserExternalIdentity::create([
            'user_id' => $user->id,
            'platform' => Platform::Odoo,
            'external_id' => '1',
        ]);
        LeaveType::create(['odoo_leave_type_id' => 1, 'name' => 'Vacation', 'active' => true]);

        // Leave within sync range that will be kept
        UserLeave::create([
            'odoo_leave_id' => 100,
            'user_id' => $user->id,
            'leave_type_id' => 1,
            'start_date' => '2024-01-15 00:00:00',
            'end_date' => '2024-01-20 23:59:59',
            'duration_days' => 5,
            'status' => 'validate',
            'type' => 'employee',
        ]);

        // Leave within sync range that should be deleted (not in API response)
        UserLeave::create([
            'odoo_leave_id' => 200,
            'user_id' => $user->id,
            'leave_type_id' => 1,
            'start_date' => '2024-01-10 00:00:00',
            'end_date' => '2024-01-12 23:59:59',
            'duration_days' => 2,
            'status' => 'validate',
            'type' => 'employee',
        ]);

        // Leave outside sync range - should NOT be deleted
        UserLeave::create([
            'odoo_leave_id' => 300,
            'user_id' => $user->id,
            'leave_type_id' => 1,
            'start_date' => '2024-02-15 00:00:00',
            'end_date' => '2024-02-20 23:59:59',
            'duration_days' => 5,
            'status' => 'validate',
            'type' => 'employee',
        ]);

        $mockOdoo = mock(OdooApiClient::class);
        $mockOdoo->shouldReceive('getLeaves')
            ->once()
            ->andReturn(collect([
                new OdooLeaveDTO(
                    id: 100,
                    holiday_type: 'employee',
                    date_from: '2024-01-15 00:00:00',
                    date_to: '2024-01-20 23:59:59',
                    number_of_days: 5.0,
                    state: 'validate',
                    holiday_status_id: [1, 'Vacation'],
                    employee_id: [1, 'Test User'],
                ),
            ]));

        $job = new SyncOdooLeavesJob($mockOdoo, '2024-01-01', '2024-01-31');
        $job->handle();

        // Leave 100 should exist (in API response)
        expect(UserLeave::where('odoo_leave_id', 100)->exists())->toBeTrue();
        // Leave 200 should be deleted (within range, not in API)
        expect(UserLeave::where('odoo_leave_id', 200)->exists())->toBeFalse();
        // Leave 300 should exist (outside sync range)
        expect(UserLeave::where('odoo_leave_id', 300)->exists())->toBeTrue();
    });
});

/*
|--------------------------------------------------------------------------
| ProofHub Sync Jobs Cleanup Tests
|--------------------------------------------------------------------------
*/

describe('SyncProofhubUsersJob cleanup', function (): void {
    it('deletes ProofHub external identities not present in API response', function (): void {
        $user1 = User::create(['name' => 'User 1', 'email' => 'user1@test.com']);
        $user2 = User::create(['name' => 'User 2', 'email' => 'user2@test.com']);

        UserExternalIdentity::create([
            'user_id' => $user1->id,
            'platform' => Platform::ProofHub,
            'external_id' => '100',
            'external_email' => 'user1@test.com',
        ]);
        UserExternalIdentity::create([
            'user_id' => $user2->id,
            'platform' => Platform::ProofHub,
            'external_id' => '200',
            'external_email' => 'user2@test.com',
        ]);

        $mockProofhub = mock(ProofhubApiClient::class);
        $mockProofhub->shouldReceive('getUsers')
            ->once()
            ->andReturn(collect([
                new ProofhubUserDTO(id: 100, email: 'user1@test.com', first_name: 'User', last_name: '1'),
            ]));

        $job = new SyncProofhubUsersJob($mockProofhub);
        $job->handle(
            app(\App\Actions\Proofhub\ProcessProofhubUserAction::class),
            app(\App\Services\NotificationService::class)
        );

        // Identity for user1 should exist
        expect(UserExternalIdentity::where('platform', Platform::ProofHub)
            ->where('external_id', '100')->exists())->toBeTrue();
        // Identity for user2 should be deleted
        expect(UserExternalIdentity::where('platform', Platform::ProofHub)
            ->where('external_id', '200')->exists())->toBeFalse();
    });
});

describe('SyncProofhubProjectsJob cleanup', function (): void {
    it('deletes projects and their dependencies not present in API response', function (): void {
        $user = User::create(['name' => 'Test User', 'email' => 'test@test.com']);

        // Create project that will be kept
        Project::create(['proofhub_project_id' => 100, 'title' => 'Active Project']);

        // Create project that will be deleted
        Project::create(['proofhub_project_id' => 200, 'title' => 'Removed Project']);

        // Create task for project 200
        Task::create([
            'proofhub_task_id' => 1000,
            'proofhub_project_id' => 200,
            'title' => 'Task in Removed Project',
        ]);

        // Create time entry for project 200
        TimeEntry::create([
            'proofhub_time_entry_id' => 5000,
            'user_id' => $user->id,
            'proofhub_project_id' => 200,
            'date' => '2024-01-15',
            'duration_seconds' => 3600,
        ]);

        $mockProofhub = mock(ProofhubApiClient::class);
        $mockProofhub->shouldReceive('getProjects')
            ->once()
            ->andReturn(collect([
                new ProofhubProjectDTO(id: 100, title: 'Active Project'),
            ]));

        $job = new SyncProofhubProjectsJob($mockProofhub);
        $job->handle();

        // Project 100 should exist
        expect(Project::where('proofhub_project_id', 100)->exists())->toBeTrue();
        // Project 200 should be deleted
        expect(Project::where('proofhub_project_id', 200)->exists())->toBeFalse();
        // Task in project 200 should be deleted
        expect(Task::where('proofhub_task_id', 1000)->exists())->toBeFalse();
        // Time entry in project 200 should be deleted
        expect(TimeEntry::where('proofhub_time_entry_id', 5000)->exists())->toBeFalse();
    });
});

describe('SyncProofhubTasksJob cleanup', function (): void {
    it('deletes tasks not present in API response and nullifies time entry references', function (): void {
        $user = User::create(['name' => 'Test User', 'email' => 'test@test.com']);
        Project::create(['proofhub_project_id' => 100, 'title' => 'Project']);

        // Create tasks
        Task::create([
            'proofhub_task_id' => 1000,
            'proofhub_project_id' => 100,
            'title' => 'Active Task',
        ]);
        Task::create([
            'proofhub_task_id' => 2000,
            'proofhub_project_id' => 100,
            'title' => 'Removed Task',
        ]);

        // Create time entry referencing the removed task
        TimeEntry::create([
            'proofhub_time_entry_id' => 5000,
            'user_id' => $user->id,
            'proofhub_project_id' => 100,
            'proofhub_task_id' => 2000,
            'date' => '2024-01-15',
            'duration_seconds' => 3600,
        ]);

        $mockProofhub = mock(ProofhubApiClient::class);
        $mockProofhub->shouldReceive('getTasks')
            ->once()
            ->andReturn(collect([
                new ProofhubTaskDTO(id: 1000, project_id: 100, title: 'Active Task'),
            ]));

        $job = new SyncProofhubTasksJob($mockProofhub);
        $job->handle();

        // Task 1000 should exist
        expect(Task::where('proofhub_task_id', 1000)->exists())->toBeTrue();
        // Task 2000 should be deleted
        expect(Task::where('proofhub_task_id', 2000)->exists())->toBeFalse();
        // Time entry should still exist but with nullified task reference
        $timeEntry = TimeEntry::find(5000);
        expect($timeEntry)->not->toBeNull();
        expect($timeEntry->proofhub_task_id)->toBeNull();
    });
});

describe('SyncProofhubTimeEntriesJob cleanup', function (): void {
    it('deletes time entries not present in API response within date range', function (): void {
        $user = User::create(['name' => 'Test User', 'email' => 'test@test.com']);
        UserExternalIdentity::create([
            'user_id' => $user->id,
            'platform' => Platform::ProofHub,
            'external_id' => '1',
        ]);
        Project::create(['proofhub_project_id' => 100, 'title' => 'Project']);

        // Time entry within sync range that will be kept
        TimeEntry::create([
            'proofhub_time_entry_id' => 5000,
            'user_id' => $user->id,
            'proofhub_project_id' => 100,
            'date' => '2024-01-15',
            'duration_seconds' => 3600,
            'status' => 'approved',
        ]);

        // Time entry within sync range that should be deleted
        TimeEntry::create([
            'proofhub_time_entry_id' => 6000,
            'user_id' => $user->id,
            'proofhub_project_id' => 100,
            'date' => '2024-01-20',
            'duration_seconds' => 3600,
            'status' => 'approved',
        ]);

        // Time entry outside sync range - should NOT be deleted
        TimeEntry::create([
            'proofhub_time_entry_id' => 7000,
            'user_id' => $user->id,
            'proofhub_project_id' => 100,
            'date' => '2024-02-15',
            'duration_seconds' => 3600,
            'status' => 'approved',
        ]);

        $mockProofhub = mock(ProofhubApiClient::class);
        $mockProofhub->shouldReceive('getTimeEntries')
            ->once()
            ->andReturn(collect([
                new ProofhubTimeEntryDTO(
                    id: 5000,
                    date: '2024-01-15',
                    logged_hours: 1,
                    logged_mins: 0,
                    project: ['id' => 100],
                    creator: ['id' => 1],
                    status: 'approved',
                ),
            ]));

        $job = new SyncProofhubTimeEntriesJob($mockProofhub, '2024-01-01', '2024-01-31');
        $job->handle();

        // Entry 5000 should exist (in API response)
        expect(TimeEntry::where('proofhub_time_entry_id', 5000)->exists())->toBeTrue();
        // Entry 6000 should be deleted (within range, not in API)
        expect(TimeEntry::where('proofhub_time_entry_id', 6000)->exists())->toBeFalse();
        // Entry 7000 should exist (outside sync range)
        expect(TimeEntry::where('proofhub_time_entry_id', 7000)->exists())->toBeTrue();
    });
});

/*
|--------------------------------------------------------------------------
| SystemPin Sync Jobs Cleanup Tests
|--------------------------------------------------------------------------
*/

describe('SyncSystemPinUsersJob cleanup', function (): void {
    it('deletes SystemPin external identities not present in API response', function (): void {
        $user1 = User::create(['name' => 'User 1', 'email' => 'user1@test.com']);
        $user2 = User::create(['name' => 'User 2', 'email' => 'user2@test.com']);

        UserExternalIdentity::create([
            'user_id' => $user1->id,
            'platform' => Platform::SystemPin,
            'external_id' => '100',
        ]);
        UserExternalIdentity::create([
            'user_id' => $user2->id,
            'platform' => Platform::SystemPin,
            'external_id' => '200',
        ]);

        $mockSystemPin = mock(SystemPinApiClient::class);
        $mockSystemPin->shouldReceive('getAllEmployees')
            ->once()
            ->andReturn(collect([
                new SystemPinUserDTO(id: 100, Nombre: 'User 1'),
            ]));

        $job = new SyncSystemPinUsersJob($mockSystemPin);
        $job->handle(
            app(\App\Actions\SystemPin\ProcessSystemPinUserAction::class),
            app(\App\Services\NotificationService::class)
        );

        // Identity for user1 should exist
        expect(UserExternalIdentity::where('platform', Platform::SystemPin)
            ->where('external_id', '100')->exists())->toBeTrue();
        // Identity for user2 should be deleted
        expect(UserExternalIdentity::where('platform', Platform::SystemPin)
            ->where('external_id', '200')->exists())->toBeFalse();
    });
});
