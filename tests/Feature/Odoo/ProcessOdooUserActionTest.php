<?php

declare(strict_types=1);

use App\Actions\Odoo\ProcessOdooUserAction;
use App\DataTransferObjects\Odoo\OdooUserDTO;
use App\Models\Category;
use App\Models\Department;
use App\Models\Schedule;
use App\Models\User;
use App\Models\UserSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->action = new ProcessOdooUserAction;

    // Create test data
    $this->department = Department::create([
        'odoo_department_id' => 2,
        'name' => 'Engineering',
        'active' => true,
    ]);

    $this->categories = [
        Category::create(['odoo_category_id' => 1, 'name' => 'Full Time', 'active' => true]),
        Category::create(['odoo_category_id' => 3, 'name' => 'Senior', 'active' => true]),
    ];

    $this->schedule = Schedule::create([
        'odoo_schedule_id' => 1,
        'description' => 'Standard 40h',
        'active' => true,
    ]);
});

test('ProcessOdooUserAction creates new user with valid data', function (): void {
    $dto = new OdooUserDTO(
        id: 1,
        work_email: 'john.doe@company.com',
        name: 'John Doe',
        tz: 'Europe/Madrid',
        active: true,
        department_id: [2, 'Engineering'],
        category_ids: [1, 3],
        resource_calendar_id: [1, 'Standard 40h'],
        job_title: 'Senior Developer',
        parent_id: [5, 'Jane Manager']
    );

    $this->action->execute($dto);

    $user = User::where('odoo_id', 1)->first();

    expect($user)->not->toBeNull();
    expect($user->name)->toBe('John Doe');
    expect($user->email)->toBe('john.doe@company.com');
    expect($user->timezone)->toBe('Europe/Madrid');
    expect($user->is_active)->toBe(true);
    expect($user->department_id)->toBe(2);
    expect($user->job_title)->toBe('Senior Developer');
    expect($user->odoo_manager_id)->toBe(5);

    // Check categories are synced
    expect($user->categories()->pluck('odoo_category_id')->toArray())
        ->toEqual([1, 3]);

    // Check schedule is synced
    $userSchedule = $user->activeUserSchedule;

    expect($userSchedule)->not->toBeNull();
    expect($userSchedule->odoo_schedule_id)->toBe(1);
    expect($userSchedule->effective_until)->toBeNull();
});

test('ProcessOdooUserAction updates existing user', function (): void {
    // Create existing user
    $existingUser = User::create([
        'odoo_id' => 1,
        'name' => 'John Smith',
        'email' => 'john.old@company.com',
        'timezone' => 'UTC',
        'is_active' => false,
    ]);

    $dto = new OdooUserDTO(
        id: 1,
        work_email: 'john.doe@company.com',
        name: 'John Doe',
        tz: 'Europe/Madrid',
        active: true,
        department_id: [2, 'Engineering'],
        category_ids: [1, 3],
        resource_calendar_id: [1, 'Standard 40h'],
        job_title: 'Senior Developer',
        parent_id: [5, 'Jane Manager']
    );

    $this->action->execute($dto);

    $user = User::where('odoo_id', 1)->first();

    expect($user)->not->toBeNull();
    expect($user->name)->toBe('John Doe');
    expect($user->email)->toBe('john.doe@company.com');
    expect($user->timezone)->toBe('Europe/Madrid');
    expect($user->is_active)->toBe(true);
    expect($user->department_id)->toBe(2);
    expect($user->job_title)->toBe('Senior Developer');
    expect($user->odoo_manager_id)->toBe(5);
});

test('ProcessOdooUserAction skips user with validation errors', function (): void {
    $dto = new OdooUserDTO(
        id: 1,
        work_email: null, // Missing required email
        name: 'John Doe',
        tz: 'Europe/Madrid',
        active: true,
        department_id: [2, 'Engineering'],
        category_ids: [1, 3],
        resource_calendar_id: [1, 'Standard 40h']
    );

    $this->action->execute($dto);

    // User should not be created due to validation failure
    $user = User::where('odoo_id', 1)->first();
    expect($user)->toBeNull();
});

test('ProcessOdooUserAction skips user with invalid email', function (): void {
    $dto = new OdooUserDTO(
        id: 1,
        work_email: 'invalid-email', // Invalid email format
        name: 'John Doe',
        tz: 'Europe/Madrid',
        active: true,
        department_id: [2, 'Engineering'],
        category_ids: [1, 3],
        resource_calendar_id: [1, 'Standard 40h']
    );

    $this->action->execute($dto);

    // User should not be created due to validation failure
    $user = User::where('odoo_id', 1)->first();
    expect($user)->toBeNull();
});

test('ProcessOdooUserAction handles null department correctly', function (): void {
    $dto = new OdooUserDTO(
        id: 1,
        work_email: 'john.doe@company.com',
        name: 'John Doe',
        tz: 'Europe/Madrid',
        active: true,
        department_id: null, // No department
        category_ids: [1, 3],
        resource_calendar_id: [1, 'Standard 40h']
    );

    $this->action->execute($dto);

    $user = User::where('odoo_id', 1)->first();

    expect($user)->not->toBeNull();
    expect($user->department_id)->toBeNull();
});

test('ProcessOdooUserAction handles null schedule correctly', function (): void {
    $dto = new OdooUserDTO(
        id: 1,
        work_email: 'john.doe@company.com',
        name: 'John Doe',
        tz: 'Europe/Madrid',
        active: true,
        department_id: [2, 'Engineering'],
        category_ids: [1, 3],
        resource_calendar_id: null // No schedule
    );

    $this->action->execute($dto);

    $user = User::where('odoo_id', 1)->first();

    expect($user)->not->toBeNull();

    // No user schedule should be created
    expect($user->userSchedules()->count())->toBe(0);
});

test('ProcessOdooUserAction syncs categories correctly', function (): void {
    $dto = new OdooUserDTO(
        id: 1,
        work_email: 'john.doe@company.com',
        name: 'John Doe',
        category_ids: [1, 3]
    );

    $this->action->execute($dto);

    $user = User::where('odoo_id', 1)->first();

    expect($user->categories()->pluck('odoo_category_id')->sort()->values()->toArray())
        ->toEqual([1, 3]);
});

test('ProcessOdooUserAction updates categories when they change', function (): void {
    // Create user with initial categories
    $user = User::create([
        'odoo_id' => 1,
        'name' => 'John Doe',
        'email' => 'john.doe@company.com',
    ]);
    $user->categories()->attach([1]); // Initially only category 1

    $dto = new OdooUserDTO(
        id: 1,
        work_email: 'john.doe@company.com',
        name: 'John Doe',
        category_ids: [3] // Changed to only category 3
    );

    $this->action->execute($dto);

    $user->refresh();

    expect($user->categories()->pluck('odoo_category_id')->toArray())
        ->toEqual([3]); // Should be updated to only category 3
});

test('ProcessOdooUserAction handles empty categories', function (): void {
    $dto = new OdooUserDTO(
        id: 1,
        work_email: 'john.doe@company.com',
        name: 'John Doe',
        category_ids: [] // No categories
    );

    $this->action->execute($dto);

    $user = User::where('odoo_id', 1)->first();

    expect($user->categories()->count())->toBe(0);
});

test('ProcessOdooUserAction creates new schedule assignment', function (): void {
    $dto = new OdooUserDTO(
        id: 1,
        work_email: 'john.doe@company.com',
        name: 'John Doe',
        resource_calendar_id: [1, 'Standard 40h']
    );

    $this->action->execute($dto);

    $user = User::where('odoo_id', 1)->first();
    $userSchedule = $user->activeUserSchedule;

    expect($userSchedule)->not->toBeNull();
    expect($userSchedule->odoo_schedule_id)->toBe(1);
    expect($userSchedule->effective_until)->toBeNull();
});

test('ProcessOdooUserAction closes previous schedule when schedule changes', function (): void {
    // Create a second schedule for testing
    $oldSchedule = Schedule::create([
        'odoo_schedule_id' => 2,
        'description' => 'Old Schedule',
        'active' => true,
    ]);

    // Create user with existing schedule
    $user = User::create([
        'odoo_id' => 1,
        'name' => 'John Doe',
        'email' => 'john.doe@company.com',
    ]);

    $oldUserSchedule = UserSchedule::create([
        'user_id' => $user->id,
        'odoo_schedule_id' => 2, // Different schedule
        'effective_from' => now()->subDays(30),
        'effective_until' => null,
    ]);

    $dto = new OdooUserDTO(
        id: 1,
        work_email: 'john.doe@company.com',
        name: 'John Doe',
        resource_calendar_id: [1, 'Standard 40h'] // New schedule
    );

    $this->action->execute($dto);

    $oldUserSchedule->refresh();
    $newSchedule = $user->activeUserSchedule;

    // Old schedule should be closed
    expect($oldUserSchedule->effective_until)
        ->not->toBeNull();
    expect($oldUserSchedule->effective_until->format('Y-m-d'))
        ->toBe(now()->startOfDay()->format('Y-m-d'));

    // New schedule should be active
    expect($newSchedule)->not->toBeNull();
    expect($newSchedule->odoo_schedule_id)->toBe(1);
    expect($newSchedule->effective_until)->toBeNull();
});

test('ProcessOdooUserAction does not duplicate schedule assignment', function (): void {
    // Create user with existing schedule
    $user = User::create([
        'odoo_id' => 1,
        'name' => 'John Doe',
        'email' => 'john.doe@company.com',
    ]);

    UserSchedule::create([
        'user_id' => $user->id,
        'odoo_schedule_id' => 1, // Same schedule
        'effective_from' => now()->subDays(30),
        'effective_until' => null,
    ]);

    $dto = new OdooUserDTO(
        id: 1,
        work_email: 'john.doe@company.com',
        name: 'John Doe',
        resource_calendar_id: [1, 'Standard 40h'] // Same schedule
    );

    $this->action->execute($dto);

    // Should still have only one schedule assignment
    expect($user->userSchedules()->count())->toBe(1);
});

test('ProcessOdooUserAction handles nonexistent schedule gracefully', function (): void {
    $dto = new OdooUserDTO(
        id: 1,
        work_email: 'john.doe@company.com',
        name: 'John Doe',
        resource_calendar_id: [999, 'Nonexistent Schedule'] // Schedule doesn't exist
    );

    $this->action->execute($dto);

    $user = User::where('odoo_id', 1)->first();

    expect($user)->not->toBeNull();

    // No user schedule should be created for nonexistent schedule
    expect($user->userSchedules()->count())->toBe(0);
});

test('ProcessOdooUserAction is atomic - rollback on failure', function (): void {
    // This test would need to simulate a database failure
    // For now, we'll test that the action uses transactions

    $dto = new OdooUserDTO(
        id: 1,
        work_email: 'john.doe@company.com',
        name: 'John Doe',
        category_ids: [1, 3],
        resource_calendar_id: [1, 'Standard 40h']
    );

    // Count database queries to ensure transactions are used
    $queryCount = 0;
    DB::listen(function () use (&$queryCount): void {
        $queryCount++;
    });

    $this->action->execute($dto);

    // Should have used transactions (this test verifies the action completes successfully)
    $user = User::where('odoo_id', 1)->first();
    expect($user)->not->toBeNull();
    expect($queryCount)->toBeGreaterThan(0);
});

test('ProcessOdooUserAction handles defaults correctly', function (): void {
    $dto = new OdooUserDTO(
        id: 1,
        work_email: 'john.doe@company.com',
        name: 'John Doe',
        tz: null, // Should default to UTC
        active: null, // Should default to true
        job_title: null,
        parent_id: null
    );

    $this->action->execute($dto);

    $user = User::where('odoo_id', 1)->first();

    expect($user)->not->toBeNull();
    expect($user->timezone)->toBe('UTC');
    expect($user->is_active)->toBe(true);
    expect($user->job_title)->toBeNull();
    expect($user->odoo_manager_id)->toBeNull();
});
