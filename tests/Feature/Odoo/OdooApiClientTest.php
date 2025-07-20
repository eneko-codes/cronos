<?php

declare(strict_types=1);

use App\Clients\OdooApiClient;
use App\DataTransferObjects\Odoo\OdooCategoryDTO;
use App\DataTransferObjects\Odoo\OdooDepartmentDTO;
use App\DataTransferObjects\Odoo\OdooLeaveDTO;
use App\DataTransferObjects\Odoo\OdooLeaveTypeDTO;
use App\DataTransferObjects\Odoo\OdooScheduleDetailDTO;
use App\DataTransferObjects\Odoo\OdooScheduleDTO;
use App\DataTransferObjects\Odoo\OdooUserDTO;
use App\Exceptions\ApiConnectionException;
use App\Exceptions\ApiResponseException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->baseUrl = 'https://test-odoo.company.com';
    $this->database = 'test_db';
    $this->username = 'test_user';
    $this->password = 'test_password';

    $this->client = new OdooApiClient(
        $this->baseUrl,
        $this->database,
        $this->username,
        $this->password
    );
});

// Authentication Tests
test('client constructor validates required parameters', function (): void {
    expect(fn () => new OdooApiClient('', 'db', 'user', 'pass'))
        ->toThrow(ApiConnectionException::class, 'Odoo API configuration is incomplete.');

    expect(fn () => new OdooApiClient('url', '', 'user', 'pass'))
        ->toThrow(ApiConnectionException::class, 'Odoo API configuration is incomplete.');

    expect(fn () => new OdooApiClient('url', 'db', '', 'pass'))
        ->toThrow(ApiConnectionException::class, 'Odoo API configuration is incomplete.');

    expect(fn () => new OdooApiClient('url', 'db', 'user', ''))
        ->toThrow(ApiConnectionException::class, 'Odoo API configuration is incomplete.');
});

test('ping returns success when api is reachable', function (): void {
    $versionResponse = [
        'jsonrpc' => '2.0',
        'id' => 'version_request_123',
        'result' => [
            'server_version' => '13.0',
            'server_version_info' => [13, 0, 0, 'final', 0],
            'server_serie' => '13.0',
            'protocol_version' => 1,
        ],
    ];

    Http::fake([
        $this->baseUrl.'/jsonrpc' => Http::response($versionResponse, 200),
    ]);

    $result = $this->client->ping();

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', true)
        ->toHaveKey('message', 'Odoo API is reachable.')
        ->toHaveKey('version', '13.0');
});

test('ping returns failure when api is not reachable', function (): void {
    Http::fake([
        $this->baseUrl.'/jsonrpc' => Http::response([], 500),
    ]);

    $result = $this->client->ping();

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', false)
        ->toHaveKey('message');
});

test('getServerVersion returns version info when successful', function (): void {
    $versionResponse = [
        'jsonrpc' => '2.0',
        'id' => 'version_request_123',
        'result' => [
            'server_version' => '13.0',
            'server_version_info' => [13, 0, 0, 'final', 0],
            'server_serie' => '13.0',
            'protocol_version' => 1,
        ],
    ];

    Http::fake([
        $this->baseUrl.'/jsonrpc' => Http::response($versionResponse, 200),
    ]);

    $result = $this->client->getServerVersion();

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', true)
        ->toHaveKey('version', '13.0');
});

// Users (hr.employee) Tests
test('getUsers returns collection of OdooUserDTO objects', function (): void {
    $usersData = [
        [
            'id' => 1,
            'work_email' => 'john.doe@company.com',
            'name' => 'John Doe',
            'tz' => 'Europe/Madrid',
            'active' => true,
            'department_id' => [2, 'Engineering'],
            'category_ids' => [1, 3],
            'resource_calendar_id' => [1, 'Standard 40h'],
            'job_title' => 'Senior Developer',
            'parent_id' => [5, 'Jane Manager'],
        ],
        [
            'id' => 2,
            'work_email' => 'alice.smith@company.com',
            'name' => 'Alice Smith',
            'tz' => 'America/New_York',
            'active' => true,
            'department_id' => [3, 'Marketing'],
            'category_ids' => [2],
            'resource_calendar_id' => [2, 'Flexible 35h'],
            'job_title' => 'Marketing Specialist',
            'parent_id' => false,
        ],
        [
            'id' => 3,
            'work_email' => false,
            'name' => 'Bob Wilson',
            'tz' => false,
            'active' => false,
            'department_id' => false,
            'category_ids' => [],
            'resource_calendar_id' => false,
            'job_title' => false,
            'parent_id' => false,
        ],
    ];

    // Mock authentication request
    Http::fake([
        $this->baseUrl.'/jsonrpc' => Http::sequence()
            ->push(['jsonrpc' => '2.0', 'id' => 'auth', 'result' => 1], 200) // Auth
            ->push(['jsonrpc' => '2.0', 'id' => 'data', 'result' => $usersData], 200), // Data
    ]);

    $users = $this->client->getUsers();

    expect($users)
        ->toBeInstanceOf(\Illuminate\Support\Collection::class)
        ->toHaveCount(3);

    expect($users->first())
        ->toBeInstanceOf(OdooUserDTO::class)
        ->toHaveProperty('id', 1)
        ->toHaveProperty('work_email', 'john.doe@company.com')
        ->toHaveProperty('name', 'John Doe')
        ->toHaveProperty('tz', 'Europe/Madrid')
        ->toHaveProperty('active', true)
        ->toHaveProperty('department_id', [2, 'Engineering'])
        ->toHaveProperty('category_ids', [1, 3])
        ->toHaveProperty('resource_calendar_id', [1, 'Standard 40h'])
        ->toHaveProperty('job_title', 'Senior Developer')
        ->toHaveProperty('parent_id', [5, 'Jane Manager']);

    // Test user with false values (Odoo quirk)
    expect($users->last())
        ->toBeInstanceOf(OdooUserDTO::class)
        ->toHaveProperty('id', 3)
        ->toHaveProperty('work_email', null)
        ->toHaveProperty('tz', null)
        ->toHaveProperty('active', false)
        ->toHaveProperty('department_id', null)
        ->toHaveProperty('category_ids', [])
        ->toHaveProperty('job_title', null);
});

// Departments (hr.department) Tests
test('getDepartments returns collection of OdooDepartmentDTO objects', function (): void {
    $departmentsData = [
        [
            'id' => 1,
            'name' => 'Company',
            'active' => true,
            'manager_id' => [10, 'CEO Name'],
            'parent_id' => false,
        ],
        [
            'id' => 2,
            'name' => 'Engineering',
            'active' => true,
            'manager_id' => [5, 'Jane Manager'],
            'parent_id' => [1, 'Company'],
        ],
        [
            'id' => 3,
            'name' => 'Marketing',
            'active' => true,
            'manager_id' => false,
            'parent_id' => [1, 'Company'],
        ],
    ];

    Http::fake([
        $this->baseUrl.'/jsonrpc' => Http::sequence()
            ->push(['jsonrpc' => '2.0', 'id' => 'auth', 'result' => 1], 200)
            ->push(['jsonrpc' => '2.0', 'id' => 'data', 'result' => $departmentsData], 200),
    ]);

    $departments = $this->client->getDepartments();

    expect($departments)
        ->toBeInstanceOf(\Illuminate\Support\Collection::class)
        ->toHaveCount(3);

    expect($departments->first())
        ->toBeInstanceOf(OdooDepartmentDTO::class)
        ->toHaveProperty('id', 1)
        ->toHaveProperty('name', 'Company')
        ->toHaveProperty('active', true)
        ->toHaveProperty('manager_id', [10, 'CEO Name'])
        ->toHaveProperty('parent_id', null);
});

// Categories (hr.employee.category) Tests
test('getCategories returns collection of OdooCategoryDTO objects', function (): void {
    $categoriesData = [
        [
            'id' => 1,
            'name' => 'Full Time',
            'active' => true,
        ],
        [
            'id' => 2,
            'name' => 'Part Time',
            'active' => true,
        ],
        [
            'id' => 3,
            'name' => 'Contractor',
            'active' => true,
        ],
    ];

    Http::fake([
        $this->baseUrl.'/jsonrpc' => Http::sequence()
            ->push(['jsonrpc' => '2.0', 'id' => 'auth', 'result' => 1], 200)
            ->push(['jsonrpc' => '2.0', 'id' => 'data', 'result' => $categoriesData], 200),
    ]);

    $categories = $this->client->getCategories();

    expect($categories)
        ->toBeInstanceOf(\Illuminate\Support\Collection::class)
        ->toHaveCount(3);

    expect($categories->first())
        ->toBeInstanceOf(OdooCategoryDTO::class)
        ->toHaveProperty('id', 1)
        ->toHaveProperty('name', 'Full Time')
        ->toHaveProperty('active', true);
});

// Leave Types (hr.leave.type) Tests
test('getLeaveTypes returns collection of OdooLeaveTypeDTO objects', function (): void {
    $leaveTypesData = [
        [
            'id' => 1,
            'name' => 'Paid Time Off',
            'request_unit' => 'day',
            'active' => true,
            'create_date' => '2024-01-01 09:00:00',
            'write_date' => '2024-01-15 14:30:00',
        ],
        [
            'id' => 2,
            'name' => 'Sick Leave',
            'request_unit' => 'half_day',
            'active' => true,
            'create_date' => '2024-01-01 09:00:00',
            'write_date' => '2024-02-01 10:00:00',
        ],
        [
            'id' => 3,
            'name' => 'Personal Leave',
            'request_unit' => 'hour',
            'active' => false,
            'create_date' => '2024-01-01 09:00:00',
            'write_date' => false,
        ],
    ];

    Http::fake([
        $this->baseUrl.'/jsonrpc' => Http::sequence()
            ->push(['jsonrpc' => '2.0', 'id' => 'auth', 'result' => 1], 200)
            ->push(['jsonrpc' => '2.0', 'id' => 'data', 'result' => $leaveTypesData], 200),
    ]);

    $leaveTypes = $this->client->getLeaveTypes();

    expect($leaveTypes)
        ->toBeInstanceOf(\Illuminate\Support\Collection::class)
        ->toHaveCount(3);

    expect($leaveTypes->first())
        ->toBeInstanceOf(OdooLeaveTypeDTO::class)
        ->toHaveProperty('id', 1)
        ->toHaveProperty('name', 'Paid Time Off')
        ->toHaveProperty('request_unit', 'day')
        ->toHaveProperty('active', true)
        ->toHaveProperty('create_date', '2024-01-01 09:00:00')
        ->toHaveProperty('write_date', '2024-01-15 14:30:00');

    // Test false values
    expect($leaveTypes->last())
        ->toHaveProperty('write_date', null);
});

// Leaves (hr.leave) Tests
test('getLeaves returns collection of OdooLeaveDTO objects', function (): void {
    $leavesData = [
        [
            'id' => 10,
            'holiday_type' => 'employee',
            'date_from' => '2024-07-01 09:00:00',
            'date_to' => '2024-07-05 18:00:00',
            'employee_id' => [1, 'John Doe'],
            'holiday_status_id' => [1, 'Paid Time Off'],
            'state' => 'validate',
            'number_of_days' => 5.0,
            'category_id' => false,
            'department_id' => false,
            'request_hour_from' => 9.0,
            'request_hour_to' => 18.0,
        ],
        [
            'id' => 11,
            'holiday_type' => 'category',
            'date_from' => '2024-07-15 14:00:00',
            'date_to' => '2024-07-15 18:00:00',
            'employee_id' => false,
            'holiday_status_id' => [2, 'Sick Leave'],
            'state' => 'confirm',
            'number_of_days' => 0.5,
            'category_id' => [1, 'Full Time'],
            'department_id' => false,
            'request_hour_from' => 14.0,
            'request_hour_to' => 18.0,
        ],
    ];

    Http::fake([
        $this->baseUrl.'/jsonrpc' => Http::sequence()
            ->push(['jsonrpc' => '2.0', 'id' => 'auth', 'result' => 1], 200)
            ->push(['jsonrpc' => '2.0', 'id' => 'data', 'result' => $leavesData], 200),
    ]);

    $leaves = $this->client->getLeaves();

    expect($leaves)
        ->toBeInstanceOf(\Illuminate\Support\Collection::class)
        ->toHaveCount(2);

    expect($leaves->first())
        ->toBeInstanceOf(OdooLeaveDTO::class)
        ->toHaveProperty('id', 10)
        ->toHaveProperty('holiday_type', 'employee')
        ->toHaveProperty('date_from', '2024-07-01 09:00:00')
        ->toHaveProperty('date_to', '2024-07-05 18:00:00')
        ->toHaveProperty('employee_id', [1, 'John Doe'])
        ->toHaveProperty('holiday_status_id', [1, 'Paid Time Off'])
        ->toHaveProperty('state', 'validate')
        ->toHaveProperty('number_of_days', 5.0)
        ->toHaveProperty('request_hour_from', 9.0)
        ->toHaveProperty('request_hour_to', 18.0);
});

test('getLeaves with date range filters requests correctly', function (): void {
    Http::fake([
        $this->baseUrl.'/jsonrpc' => Http::sequence()
            ->push(['jsonrpc' => '2.0', 'id' => 'auth', 'result' => 1], 200)
            ->push(['jsonrpc' => '2.0', 'id' => 'data', 'result' => []], 200),
    ]);

    $this->client->getLeaves('2024-07-01', '2024-07-31');

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        // Skip authentication requests
        if (isset($body['params']['service']) && $body['params']['service'] === 'common') {
            return true;
        }

        // Check data request for date filters
        if (isset($body['params']['args'][1]) && is_array($body['params']['args'][1])) {
            $domain = $body['params']['args'][1]; // The domain parameter

            $hasDateFromFilter = false;
            $hasDateToFilter = false;

            foreach ($domain as $filter) {
                if (is_array($filter) && count($filter) === 3) {
                    if ($filter[0] === 'date_from' && $filter[1] === '<=' && $filter[2] === '2024-07-31 23:59:59') {
                        $hasDateFromFilter = true;
                    }
                    if ($filter[0] === 'date_to' && $filter[1] === '>=' && $filter[2] === '2024-07-01 00:00:00') {
                        $hasDateToFilter = true;
                    }
                }
            }

            return $hasDateFromFilter && $hasDateToFilter;
        }

        return true;
    });
});

// Schedules (resource.calendar) Tests
test('getSchedules returns collection of OdooScheduleDTO objects', function (): void {
    $schedulesData = [
        [
            'id' => 1,
            'name' => 'Standard 40h',
            'active' => true,
            'attendance_ids' => [1, 2, 3, 4, 5],
            'hours_per_day' => 8.0,
            'create_date' => '2024-01-01 09:00:00',
            'write_date' => '2024-01-10 09:00:00',
        ],
        [
            'id' => 2,
            'name' => 'Flexible 35h',
            'attendance_ids' => [6, 7, 8],
            'hours_per_day' => 7.0,
            'two_weeks_calendar' => true,
            'two_weeks_explanation' => 'Alternating week schedule',
            'flexible_hours' => true,
            'create_date' => '2024-01-01 09:00:00',
            'write_date' => '2024-02-01 12:00:00',
        ],
    ];

    Http::fake([
        $this->baseUrl.'/jsonrpc' => Http::sequence()
            ->push(['jsonrpc' => '2.0', 'id' => 'auth', 'result' => 1], 200)
            ->push(['jsonrpc' => '2.0', 'id' => 'data', 'result' => $schedulesData], 200),
    ]);

    $schedules = $this->client->getSchedules();

    expect($schedules)
        ->toBeInstanceOf(\Illuminate\Support\Collection::class)
        ->toHaveCount(2);

    expect($schedules->first())
        ->toBeInstanceOf(OdooScheduleDTO::class)
        ->toHaveProperty('id', 1)
        ->toHaveProperty('name', 'Standard 40h')
        ->toHaveProperty('active', true)
        ->toHaveProperty('attendance_ids', [1, 2, 3, 4, 5])
        ->toHaveProperty('hours_per_day', 8.0)
        ->toHaveProperty('two_weeks_calendar', null)
        ->toHaveProperty('flexible_hours', null);

    // Test with optional fields present
    expect($schedules->last())
        ->toHaveProperty('two_weeks_calendar', true)
        ->toHaveProperty('two_weeks_explanation', 'Alternating week schedule')
        ->toHaveProperty('flexible_hours', true);
});

// Schedule Details (resource.calendar.attendance) Tests
test('getScheduleDetails returns collection of OdooScheduleDetailDTO objects', function (): void {
    $scheduleDetailsData = [
        [
            'id' => 1,
            'calendar_id' => [1, 'Standard 40h'],
            'name' => 'Monday Morning',
            'dayofweek' => '0',
            'hour_from' => 9.0,
            'hour_to' => 13.0,
            'day_period' => 'morning',
            'date_from' => '2024-01-01',
            'date_to' => '2024-12-31',
            'active' => true,
            'create_date' => '2024-01-01 09:00:00',
            'write_date' => '2024-01-10 09:00:00',
        ],
        [
            'id' => 2,
            'calendar_id' => [2, 'Bi-weekly Flexible'],
            'name' => 'Tuesday - Week 1',
            'dayofweek' => '1',
            'hour_from' => 8.0,
            'hour_to' => 16.0,
            'day_period' => 'morning',
            'week_type' => 1,
            'date_from' => '2024-01-01',
            'date_to' => '2024-06-30',
            'active' => true,
            'create_date' => '2024-01-01 09:00:00',
            'write_date' => '2024-01-15 14:30:00',
        ],
    ];

    Http::fake([
        $this->baseUrl.'/jsonrpc' => Http::sequence()
            ->push(['jsonrpc' => '2.0', 'id' => 'auth', 'result' => 1], 200)
            ->push(['jsonrpc' => '2.0', 'id' => 'data', 'result' => $scheduleDetailsData], 200),
    ]);

    $scheduleDetails = $this->client->getScheduleDetails();

    expect($scheduleDetails)
        ->toBeInstanceOf(\Illuminate\Support\Collection::class)
        ->toHaveCount(2);

    expect($scheduleDetails->first())
        ->toBeInstanceOf(OdooScheduleDetailDTO::class)
        ->toHaveProperty('id', 1)
        ->toHaveProperty('calendar_id', [1, 'Standard 40h'])
        ->toHaveProperty('name', 'Monday Morning')
        ->toHaveProperty('dayofweek', '0')
        ->toHaveProperty('hour_from', 9.0)
        ->toHaveProperty('hour_to', 13.0)
        ->toHaveProperty('day_period', 'morning')
        ->toHaveProperty('week_type', null)
        ->toHaveProperty('date_from', '2024-01-01')
        ->toHaveProperty('date_to', '2024-12-31')
        ->toHaveProperty('active', true);

    // Test with week_type present (bi-weekly)
    expect($scheduleDetails->last())
        ->toHaveProperty('week_type', 1);
});

// Error Handling Tests
test('client throws ApiResponseException when odoo returns error', function (): void {
    $errorResponse = [
        'jsonrpc' => '2.0',
        'id' => 'error_request_123',
        'error' => [
            'code' => 100,
            'message' => 'AccessError: Access Denied',
            'data' => [
                'name' => 'odoo.exceptions.AccessError',
                'debug' => 'Traceback (most recent call last)...',
                'message' => 'Access Denied',
                'exception_type' => 'access_error',
            ],
        ],
    ];

    Http::fake([
        $this->baseUrl.'/jsonrpc' => Http::response($errorResponse, 200),
    ]);

    expect(fn () => $this->client->getUsers())
        ->toThrow(ApiResponseException::class, 'Odoo API Error: AccessError: Access Denied');
});

test('client throws ApiConnectionException when http request fails', function (): void {
    Http::fake([
        $this->baseUrl.'/jsonrpc' => function (): void {
            throw new \Illuminate\Http\Client\ConnectionException('Connection failed');
        },
    ]);

    expect(fn () => $this->client->getUsers())
        ->toThrow(ApiConnectionException::class, 'Failed to connect to Odoo API: Connection failed');
});

test('client throws ApiResponseException when http returns 500', function (): void {
    Http::fake([
        $this->baseUrl.'/jsonrpc' => Http::response([], 500),
    ]);

    expect(fn () => $this->client->getUsers())
        ->toThrow(ApiResponseException::class);
});

test('client throws ApiConnectionException when authentication fails', function (): void {
    Http::fake([
        $this->baseUrl.'/jsonrpc' => Http::response([
            'jsonrpc' => '2.0',
            'id' => 'auth',
            'result' => false, // Authentication failed
        ], 200),
    ]);

    expect(fn () => $this->client->getUsers())
        ->toThrow(ApiConnectionException::class, 'Odoo authentication failed: no valid user ID returned.');
});

// JSON-RPC Request Format Tests
test('client sends properly formatted json-rpc requests', function (): void {
    Http::fake([
        $this->baseUrl.'/jsonrpc' => Http::sequence()
            ->push(['jsonrpc' => '2.0', 'id' => 'auth', 'result' => 1], 200)
            ->push(['jsonrpc' => '2.0', 'id' => 'data', 'result' => []], 200),
    ]);

    $this->client->getUsers();

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        // Check JSON-RPC format
        expect($body)
            ->toHaveKey('jsonrpc', '2.0')
            ->toHaveKey('method', 'call')
            ->toHaveKey('params')
            ->toHaveKey('id');

        // Check authentication call format
        if (isset($body['params']['service']) && $body['params']['service'] === 'common') {
            expect($body['params'])
                ->toHaveKey('service', 'common')
                ->toHaveKey('method', 'authenticate')
                ->toHaveKey('args')
                ->and($body['params']['args'])
                ->toEqual(['test_db', 'test_user', 'test_password', []]);
        }

        return true;
    });
});
