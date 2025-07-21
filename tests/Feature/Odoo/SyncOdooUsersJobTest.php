<?php

declare(strict_types=1);

use App\Actions\Odoo\CheckOdooHealthAction;
use App\Clients\OdooApiClient;
use App\DataTransferObjects\Odoo\OdooUserDTO;
use App\Jobs\Sync\Odoo\SyncOdooUsersJob;
use App\Models\Category;
use App\Models\Department;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var \App\Clients\OdooApiClient&\Mockery\MockInterface */
    $this->odooClient = Mockery::namedMock('MockOdooApiClient', OdooApiClient::class);
    $this->job = new SyncOdooUsersJob($this->odooClient);

    // Create test data
    $this->department = Department::create([
        'odoo_department_id' => 5,
        'name' => 'Engineering',
        'active' => true,
    ]);
    $this->category = Category::create([
        'odoo_category_id' => 10,
        'name' => 'Full Time',
        'active' => true,
    ]);
    $this->schedule = Schedule::create([
        'odoo_schedule_id' => 1,
        'description' => 'Standard 40h',
        'active' => true,
    ]);
});

test('SyncOdooUsersJob can be constructed with OdooApiClient', function (): void {
    expect($this->job)->toBeInstanceOf(SyncOdooUsersJob::class);
    expect($this->job->priority)->toBe(1);
});

test('SyncOdooUsersJob handle method fetches and processes users', function (): void {
    // Mock users data with different configurations
    $usersData = collect([
        new OdooUserDTO(
            id: 123,
            work_email: 'john.doe@company.com',
            name: 'John Doe',
            tz: 'Europe/Madrid',
            active: true,
            department_id: [5, 'Engineering'],
            category_ids: [10],
            resource_calendar_id: [1, 'Standard 40h'],
            job_title: 'Senior Developer',
            parent_id: [456, 'Jane Manager']
        ),
        new OdooUserDTO(
            id: 456,
            work_email: 'jane.manager@company.com',
            name: 'Jane Manager',
            tz: 'Europe/Madrid',
            active: true,
            department_id: [5, 'Engineering'],
            category_ids: [10],
            resource_calendar_id: [1, 'Standard 40h'],
            job_title: 'Engineering Manager',
            parent_id: null // No manager
        ),
        new OdooUserDTO(
            id: 789,
            work_email: null, // No email
            name: 'Bob Contractor',
            tz: null, // No timezone
            active: false,
            department_id: null, // No department
            category_ids: [], // No categories
            resource_calendar_id: null, // No schedule
            job_title: 'Contractor',
            parent_id: [456, 'Jane Manager']
        ),
    ]);

    // Mock the API client to return our test data
    $this->odooClient
        ->shouldReceive('getUsers')
        ->once()
        ->andReturn($usersData);

    // Execute the job
    $this->job->handle();

    // Verify that users were created in the database (third user skipped due to missing email)
    expect(User::count())->toBe(2);

    $john = User::where('odoo_id', 123)->first();
    expect($john->email)->toBe('john.doe@company.com');
    expect($john->name)->toBe('John Doe');
    expect($john->timezone)->toBe('Europe/Madrid');
    expect($john->is_active)->toBe(true);
    expect($john->department_id)->toBe(5);
    expect($john->activeSchedule?->odoo_schedule_id)->toBe(1);
    expect($john->job_title)->toBe('Senior Developer');
    expect($john->odoo_manager_id)->toBe(456);

    $jane = User::where('odoo_id', 456)->first();
    expect($jane->email)->toBe('jane.manager@company.com');
    expect($jane->name)->toBe('Jane Manager');
    expect($jane->job_title)->toBe('Engineering Manager');
    expect($jane->odoo_manager_id)->toBeNull();

    // User 789 should not be created due to missing email validation
    expect(User::where('odoo_id', 789)->exists())->toBe(false);
});

test('SyncOdooUsersJob handle method works with empty users collection', function (): void {
    // Mock empty collection
    $this->odooClient
        ->shouldReceive('getUsers')
        ->once()
        ->andReturn(collect([]));

    // Execute the job
    $this->job->handle();

    // Verify no users were created
    expect(User::count())->toBe(0);
});

test('SyncOdooUsersJob handle method processes single user', function (): void {
    // Mock single user
    $usersData = collect([
        new OdooUserDTO(
            id: 123,
            work_email: 'john.doe@company.com',
            name: 'John Doe',
            tz: 'Europe/Madrid',
            active: true,
            department_id: [5, 'Engineering'],
            category_ids: [10],
            resource_calendar_id: [1, 'Standard 40h'],
            job_title: 'Senior Developer',
            parent_id: null
        ),
    ]);

    $this->odooClient
        ->shouldReceive('getUsers')
        ->once()
        ->andReturn($usersData);

    // Execute the job
    $this->job->handle();

    // Verify user was created
    expect(User::count())->toBe(1);

    $user = User::where('odoo_id', 123)->first();
    expect($user->email)->toBe('john.doe@company.com');
    expect($user->name)->toBe('John Doe');
    expect($user->timezone)->toBe('Europe/Madrid');
    expect($user->is_active)->toBe(true);
});

test('SyncOdooUsersJob handle method updates existing users', function (): void {
    // Create existing user
    User::factory()->create([
        'odoo_id' => 123,
        'email' => 'old.email@company.com',
        'name' => 'Old Name',
        'timezone' => 'UTC',
        'department_id' => 5,
        'job_title' => 'Old Title',
        'odoo_manager_id' => 99,
    ]);

    // Mock updated user data
    $usersData = collect([
        new OdooUserDTO(
            id: 123,
            work_email: 'john.doe@company.com',
            name: 'John Doe',
            tz: 'Europe/Madrid',
            active: true,
            department_id: [5, 'Engineering'],
            category_ids: [10],
            resource_calendar_id: [1, 'Standard 40h'],
            job_title: 'Senior Developer',
            parent_id: [456, 'Jane Manager']
        ),
    ]);

    $this->odooClient
        ->shouldReceive('getUsers')
        ->once()
        ->andReturn($usersData);

    // Execute the job
    $this->job->handle();

    // Verify user was updated, not duplicated
    expect(User::count())->toBe(1);

    $user = User::where('odoo_id', 123)->first();
    expect($user->email)->toBe('john.doe@company.com');
    expect($user->name)->toBe('John Doe');
    expect($user->timezone)->toBe('Europe/Madrid');
    expect($user->is_active)->toBe(true);
    expect($user->department_id)->toBe(5);
    expect($user->activeSchedule?->odoo_schedule_id)->toBe(1);
    expect($user->job_title)->toBe('Senior Developer');
    expect($user->odoo_manager_id)->toBe(456);
});

test('SyncOdooUsersJob failed method triggers health check', function (): void {
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

test('SyncOdooUsersJob can be dispatched to queue', function (): void {
    Queue::fake();

    // Dispatch the job
    SyncOdooUsersJob::dispatch($this->odooClient);

    // Assert job was pushed to queue
    Queue::assertPushed(SyncOdooUsersJob::class);
});

test('SyncOdooUsersJob handles API exceptions gracefully', function (): void {
    // Mock API client to throw exception
    $this->odooClient
        ->shouldReceive('getUsers')
        ->once()
        ->andThrow(new \App\Exceptions\ApiConnectionException('Connection failed'));

    // Execute the job and expect exception
    expect(fn () => $this->job->handle())
        ->toThrow(\App\Exceptions\ApiConnectionException::class, 'Connection failed');

    // Verify no users were created
    expect(User::count())->toBe(0);
});

test('SyncOdooUsersJob skips invalid user data', function (): void {
    // Mock users with one invalid entry (missing required fields)
    $usersData = collect([
        new OdooUserDTO(
            id: 123,
            work_email: 'john.doe@company.com',
            name: 'John Doe',
            tz: 'Europe/Madrid',
            active: true,
            department_id: [5, 'Engineering'],
            category_ids: [10],
            resource_calendar_id: [1, 'Standard 40h'],
            job_title: 'Senior Developer',
            parent_id: null
        ),
        new OdooUserDTO(
            id: null, // Invalid - missing ID
            work_email: 'invalid@company.com',
            name: 'Invalid User',
            tz: 'UTC',
            active: true,
            department_id: null,
            category_ids: [],
            resource_calendar_id: null,
            job_title: 'Invalid',
            parent_id: null
        ),
        new OdooUserDTO(
            id: 789,
            work_email: 'bob@company.com',
            name: 'Bob Valid',
            tz: 'UTC',
            active: true,
            department_id: null,
            category_ids: [],
            resource_calendar_id: null,
            job_title: 'Valid User',
            parent_id: null
        ),
    ]);

    $this->odooClient
        ->shouldReceive('getUsers')
        ->once()
        ->andReturn($usersData);

    // Execute the job
    $this->job->handle();

    // Verify only valid users were created (invalid one skipped)
    expect(User::count())->toBe(2);

    expect(User::where('odoo_id', 123)->exists())->toBe(true);
    expect(User::where('odoo_id', 789)->exists())->toBe(true);
    expect(User::where('email', 'invalid@company.com')->exists())->toBe(false);
});

test('SyncOdooUsersJob handles users with multiple categories', function (): void {
    // Create multiple categories
    Category::create(['odoo_category_id' => 20, 'name' => 'Part Time', 'active' => true]);
    Category::create(['odoo_category_id' => 30, 'name' => 'Remote', 'active' => true]);

    // Mock user with multiple categories
    $usersData = collect([
        new OdooUserDTO(
            id: 123,
            work_email: 'john.doe@company.com',
            name: 'John Doe',
            tz: 'Europe/Madrid',
            active: true,
            department_id: [5, 'Engineering'],
            category_ids: [10, 20, 30], // Multiple categories
            resource_calendar_id: [1, 'Standard 40h'],
            job_title: 'Senior Developer',
            parent_id: null
        ),
    ]);

    $this->odooClient
        ->shouldReceive('getUsers')
        ->once()
        ->andReturn($usersData);

    // Execute the job
    $this->job->handle();

    // Verify user was created and categories were associated
    expect(User::count())->toBe(1);

    $user = User::where('odoo_id', 123)->first();
    expect($user->categories)->toHaveCount(3);
    expect($user->categories->pluck('odoo_category_id')->toArray())->toMatchArray([10, 20, 30]);
});

test('SyncOdooUsersJob handles different timezone formats', function (): void {
    // Mock users with different timezone formats
    $usersData = collect([
        new OdooUserDTO(
            id: 123,
            work_email: 'user1@company.com',
            name: 'User Madrid',
            tz: 'Europe/Madrid',
            active: true,
            department_id: null,
            category_ids: [],
            resource_calendar_id: null,
            job_title: 'Developer',
            parent_id: null
        ),
        new OdooUserDTO(
            id: 456,
            work_email: 'user2@company.com',
            name: 'User UTC',
            tz: 'UTC',
            active: true,
            department_id: null,
            category_ids: [],
            resource_calendar_id: null,
            job_title: 'Developer',
            parent_id: null
        ),
        new OdooUserDTO(
            id: 789,
            work_email: 'user3@company.com',
            name: 'User No TZ',
            tz: null, // No timezone
            active: true,
            department_id: null,
            category_ids: [],
            resource_calendar_id: null,
            job_title: 'Developer',
            parent_id: null
        ),
    ]);

    $this->odooClient
        ->shouldReceive('getUsers')
        ->once()
        ->andReturn($usersData);

    // Execute the job
    $this->job->handle();

    // Verify all users were created with correct timezones
    expect(User::count())->toBe(3);

    expect(User::where('odoo_id', 123)->first()->timezone)->toBe('Europe/Madrid');
    expect(User::where('odoo_id', 456)->first()->timezone)->toBe('UTC');
    expect(User::where('odoo_id', 789)->first()->timezone)->toBe('UTC');
});

test('SyncOdooUsersJob processes large number of users efficiently', function (): void {
    // Create a large collection of users
    $usersData = collect();
    for ($i = 1; $i <= 100; $i++) {
        $usersData->push(new OdooUserDTO(
            id: $i,
            work_email: "user{$i}@company.com",
            name: "User {$i}",
            tz: ($i % 3 === 0) ? 'Europe/Madrid' : (($i % 3 === 1) ? 'UTC' : null),
            active: ($i % 5 !== 0), // Most active, some inactive
            department_id: ($i % 4 === 0) ? [5, 'Engineering'] : null,
            category_ids: ($i % 2 === 0) ? [10] : [],
            resource_calendar_id: ($i % 3 === 0) ? [1, 'Standard 40h'] : null,
            job_title: "Job {$i}",
            parent_id: ($i > 10) ? [($i % 10) + 1, 'Manager'] : null
        ));
    }

    $this->odooClient
        ->shouldReceive('getUsers')
        ->once()
        ->andReturn($usersData);

    // Execute the job
    $this->job->handle();

    // Verify all users were processed
    expect(User::count())->toBe(100);

    // Verify some random samples
    $user25 = User::where('odoo_id', 25)->first();
    expect($user25->name)->toBe('User 25');
    expect($user25->is_active)->toBe(false); // 25 % 5 === 0
    expect($user25->timezone)->toBe('UTC'); // 25 % 3 = 1, but tz logic is different

    $user30 = User::where('odoo_id', 30)->first();
    expect($user30->timezone)->toBe('Europe/Madrid'); // 30 % 3 === 0
    expect($user30->department_id)->toBeNull(); // 30 % 4 = 2, not 0
});

test('SyncOdooUsersJob maintains data integrity during partial failures', function (): void {
    // Create some existing users
    User::factory()->create(['odoo_id' => 123, 'name' => 'Existing 1']);
    User::factory()->create(['odoo_id' => 456, 'name' => 'Existing 2', 'is_active' => false]);

    // Mock users with mix of valid and invalid data
    $usersData = collect([
        new OdooUserDTO(
            id: 123,
            work_email: 'updated1@company.com',
            name: 'Updated Existing 1',
            tz: 'Europe/Madrid',
            active: false,
            department_id: [5, 'Engineering'],
            category_ids: [10],
            resource_calendar_id: [1, 'Standard 40h'],
            job_title: 'Updated Developer',
            parent_id: null
        ), // Update existing
        new OdooUserDTO(
            id: null, // Invalid - missing ID
            work_email: 'invalid@company.com',
            name: 'Invalid New',
            tz: 'UTC',
            active: true,
            department_id: null,
            category_ids: [],
            resource_calendar_id: null,
            job_title: 'Invalid',
            parent_id: null
        ), // Invalid new
        new OdooUserDTO(
            id: 789,
            work_email: 'new@company.com',
            name: 'Valid New User',
            tz: 'UTC',
            active: true,
            department_id: [5, 'Engineering'],
            category_ids: [10],
            resource_calendar_id: [1, 'Standard 40h'],
            job_title: 'New Developer',
            parent_id: [456, 'Manager']
        ), // Valid new
    ]);

    $this->odooClient
        ->shouldReceive('getUsers')
        ->once()
        ->andReturn($usersData);

    // Execute the job
    $this->job->handle();

    // Verify data integrity
    expect(User::count())->toBe(3); // 2 existing + 1 new valid

    // Existing user should be updated
    $updated = User::where('odoo_id', 123)->first();
    expect($updated->name)->toBe('Updated Existing 1');
    expect($updated->email)->toBe('updated1@company.com');
    expect($updated->is_active)->toBe(false);
    expect($updated->department_id)->toBe(5);

    // Second existing user should remain unchanged
    $unchanged = User::where('odoo_id', 456)->first();
    expect($unchanged->name)->toBe('Existing 2');
    expect($unchanged->is_active)->toBe(false);

    // New valid user should be created
    $newUser = User::where('odoo_id', 789)->first();
    expect($newUser->name)->toBe('Valid New User');
    expect($newUser->email)->toBe('new@company.com');
    expect($newUser->is_active)->toBe(true);
    expect($newUser->job_title)->toBe('New Developer');
    expect($newUser->odoo_manager_id)->toBe(456);

    // Invalid user should not exist
    expect(User::where('email', 'invalid@company.com')->exists())->toBe(false);
});

test('SyncOdooUsersJob handles hierarchical user structures', function (): void {
    // Mock hierarchical users (manager-employee relationships)
    $usersData = collect([
        new OdooUserDTO(
            id: 1,
            work_email: 'ceo@company.com',
            name: 'CEO',
            tz: 'Europe/Madrid',
            active: true,
            department_id: [5, 'Engineering'],
            category_ids: [10],
            resource_calendar_id: [1, 'Standard 40h'],
            job_title: 'Chief Executive Officer',
            parent_id: null // Top level
        ),
        new OdooUserDTO(
            id: 2,
            work_email: 'manager@company.com',
            name: 'Engineering Manager',
            tz: 'Europe/Madrid',
            active: true,
            department_id: [5, 'Engineering'],
            category_ids: [10],
            resource_calendar_id: [1, 'Standard 40h'],
            job_title: 'Engineering Manager',
            parent_id: [1, 'CEO'] // Reports to CEO
        ),
        new OdooUserDTO(
            id: 3,
            work_email: 'dev@company.com',
            name: 'Developer',
            tz: 'Europe/Madrid',
            active: true,
            department_id: [5, 'Engineering'],
            category_ids: [10],
            resource_calendar_id: [1, 'Standard 40h'],
            job_title: 'Software Developer',
            parent_id: [2, 'Engineering Manager'] // Reports to Engineering Manager
        ),
    ]);

    $this->odooClient
        ->shouldReceive('getUsers')
        ->once()
        ->andReturn($usersData);

    // Execute the job
    $this->job->handle();

    // Verify all users were created with correct hierarchy
    expect(User::count())->toBe(3);

    $ceo = User::where('odoo_id', 1)->first();
    expect($ceo->name)->toBe('CEO');
    expect($ceo->odoo_manager_id)->toBeNull();

    $manager = User::where('odoo_id', 2)->first();
    expect($manager->name)->toBe('Engineering Manager');
    expect($manager->odoo_manager_id)->toBe(1);

    $dev = User::where('odoo_id', 3)->first();
    expect($dev->name)->toBe('Developer');
    expect($dev->odoo_manager_id)->toBe(2);
});

test('SyncOdooUsersJob extends BaseSyncJob', function (): void {
    expect($this->job)->toBeInstanceOf(\App\Jobs\Sync\BaseSyncJob::class);
});

test('SyncOdooUsersJob has correct priority', function (): void {
    expect($this->job->priority)->toBe(1);
});

afterEach(function (): void {
    Mockery::close();
});
