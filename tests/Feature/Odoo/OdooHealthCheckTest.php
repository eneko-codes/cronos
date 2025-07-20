<?php

declare(strict_types=1);

use App\Actions\Odoo\CheckOdooHealthAction;
use App\Clients\OdooApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->client = new OdooApiClient(
        'https://test-odoo.company.com',
        'test_db',
        'test_user',
        'test_password'
    );
});

test('CheckOdooHealthAction returns success when Odoo API is reachable', function (): void {
    $versionResponse = json_decode(
        file_get_contents(base_path('tests/Fixtures/Odoo/odoo_version_response.json')),
        true
    );

    Http::fake([
        'https://test-odoo.company.com/jsonrpc' => Http::response($versionResponse, 200),
    ]);

    $action = new CheckOdooHealthAction;
    $result = $action($this->client);

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', true)
        ->toHaveKey('message', 'Odoo API is reachable.')
        ->toHaveKey('version', '13.0');
});

test('CheckOdooHealthAction returns failure when Odoo API is not reachable', function (): void {
    Http::fake([
        'https://test-odoo.company.com/jsonrpc' => Http::response([], 500),
    ]);

    $action = new CheckOdooHealthAction;
    $result = $action($this->client);

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', false)
        ->toHaveKey('message');

    expect($result['success'])->toBeFalse();
});

test('CheckOdooHealthAction handles connection timeout gracefully', function (): void {
    Http::fake([
        'https://test-odoo.company.com/jsonrpc' => function (): void {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
        },
    ]);

    $action = new CheckOdooHealthAction;
    $result = $action($this->client);

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', false)
        ->toHaveKey('message');

    expect($result['message'])->toContain('Connection timeout');
});

test('CheckOdooHealthAction handles authentication error', function (): void {
    $errorResponse = json_decode(
        file_get_contents(base_path('tests/Fixtures/Odoo/odoo_error_response.json')),
        true
    );

    Http::fake([
        'https://test-odoo.company.com/jsonrpc' => Http::response($errorResponse, 200),
    ]);

    $action = new CheckOdooHealthAction;
    $result = $action($this->client);

    expect($result)
        ->toBeArray()
        ->toHaveKey('success', false)
        ->toHaveKey('message');

    expect($result['message'])->toContain('Access Denied');
});
