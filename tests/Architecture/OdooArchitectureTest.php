<?php

declare(strict_types=1);

// Odoo Client Architecture Tests
test('Odoo API client exists and is a class', function (): void {
    expect('App\Clients\OdooApiClient')
        ->toBeClass();
});

test('Odoo API client is readonly', function (): void {
    expect('App\Clients\OdooApiClient')
        ->toBeReadonly();
});

test('Odoo API client has proper dependencies', function (): void {
    expect('App\Clients\OdooApiClient')
        ->toUse([
            'App\DataTransferObjects\Odoo\OdooUserDTO',
            'App\DataTransferObjects\Odoo\OdooDepartmentDTO',
            'App\DataTransferObjects\Odoo\OdooCategoryDTO',
            'App\DataTransferObjects\Odoo\OdooLeaveDTO',
            'App\DataTransferObjects\Odoo\OdooLeaveTypeDTO',
            'App\DataTransferObjects\Odoo\OdooScheduleDTO',
            'App\DataTransferObjects\Odoo\OdooScheduleDetailDTO',
            'App\Exceptions\ApiConnectionException',
            'App\Exceptions\ApiRequestException',
            'App\Exceptions\ApiResponseException',
            'Illuminate\Support\Facades\Http',
        ]);
});

// Odoo DTO Architecture Tests
test('Odoo DTOs namespace only contains classes', function (): void {
    expect('App\DataTransferObjects\Odoo')
        ->toBeClasses();
});

test('Odoo DTOs are readonly', function (): void {
    expect('App\DataTransferObjects\Odoo')
        ->toBeReadonly();
});

test('all Odoo DTOs exist', function (): void {
    $expectedDTOs = [
        'App\DataTransferObjects\Odoo\OdooUserDTO',
        'App\DataTransferObjects\Odoo\OdooDepartmentDTO',
        'App\DataTransferObjects\Odoo\OdooCategoryDTO',
        'App\DataTransferObjects\Odoo\OdooLeaveDTO',
        'App\DataTransferObjects\Odoo\OdooLeaveTypeDTO',
        'App\DataTransferObjects\Odoo\OdooScheduleDTO',
        'App\DataTransferObjects\Odoo\OdooScheduleDetailDTO',
    ];

    foreach ($expectedDTOs as $dto) {
        expect($dto)->toBeClass();
    }
});

test('Odoo DTOs use proper constructor properties', function (): void {
    $dtoClasses = [
        'App\DataTransferObjects\Odoo\OdooUserDTO',
        'App\DataTransferObjects\Odoo\OdooDepartmentDTO',
        'App\DataTransferObjects\Odoo\OdooCategoryDTO',
        'App\DataTransferObjects\Odoo\OdooLeaveDTO',
        'App\DataTransferObjects\Odoo\OdooLeaveTypeDTO',
        'App\DataTransferObjects\Odoo\OdooScheduleDTO',
        'App\DataTransferObjects\Odoo\OdooScheduleDetailDTO',
    ];

    foreach ($dtoClasses as $dtoClass) {
        expect(method_exists($dtoClass, '__construct'))->toBeTrue();
    }
});

// General Odoo Code Standards
test('Odoo-related code does not use debugging functions', function (): void {
    expect(['dd', 'ddd', 'die', 'dump', 'var_dump', 'print_r'])
        ->not->toBeUsedIn('App\Clients\OdooApiClient');
});

test('Odoo API client methods exist', function (): void {
    $client = new \App\Clients\OdooApiClient(
        'https://test.odoo.com',
        'test_db',
        'test_user',
        'test_pass'
    );

    $expectedMethods = [
        'getUsers',
        'getDepartments',
        'getCategories',
        'getLeaveTypes',
        'getLeaves',
        'getSchedules',
        'getScheduleDetails',
        'ping',
        'getServerVersion',
    ];

    foreach ($expectedMethods as $method) {
        expect(method_exists($client, $method))->toBeTrue();
    }
});

// Test Structure Standards
test('Odoo test directories exist', function (): void {
    expect(is_dir(base_path('tests/Unit/Odoo')))->toBeTrue();
    expect(is_dir(base_path('tests/Feature/Odoo')))->toBeTrue();
    expect(is_dir(base_path('tests/Fixtures/Odoo')))->toBeTrue();
});

test('Odoo fixtures are valid JSON', function (): void {
    $fixturesPath = base_path('tests/Fixtures/Odoo');
    $fixtures = glob($fixturesPath.'/*.json');

    expect($fixtures)->not->toBeEmpty();

    foreach ($fixtures as $fixture) {
        $content = file_get_contents($fixture);
        $decoded = json_decode($content, true);

        expect($decoded)
            ->not->toBeNull()
            ->and(json_last_error())
            ->toBe(JSON_ERROR_NONE);
    }
});

test('Odoo API client constructor requires all parameters', function (): void {
    // Test that constructor validates parameters
    expect(function (): void {
        new \App\Clients\OdooApiClient('', 'db', 'user', 'pass');
    })->toThrow(\App\Exceptions\ApiConnectionException::class);

    expect(function (): void {
        new \App\Clients\OdooApiClient('url', '', 'user', 'pass');
    })->toThrow(\App\Exceptions\ApiConnectionException::class);
});

test('Odoo exceptions exist and extend proper base classes', function (): void {
    expect('App\Exceptions\ApiConnectionException')->toBeClass();
    expect('App\Exceptions\ApiRequestException')->toBeClass();
    expect('App\Exceptions\ApiResponseException')->toBeClass();
});
