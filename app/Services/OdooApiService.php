<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Pingable;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Custom exception for Odoo API connection errors
 */
class OdooConnectionException extends Exception {}

/**
 * Custom exception for Odoo API request errors
 */
class OdooRequestException extends Exception {}

/**
 * Custom exception for Odoo API response errors
 */
class OdooResponseException extends Exception {}

/**
 * Class OdooApiService
 *
 * Handles all interactions with the Odoo API, including authentication and data retrieval.
 * This service fetches raw data from Odoo without renaming keys or setting defaults.
 *
 * Odoo 13 note: "date_from" and "date_to" in "hr.leave" are stored as UTC datetime fields.
 */
class OdooApiService implements Pingable
{
    /**
     * The Odoo base URL (e.g., https://odoo.company.com).
     */
    private string $baseUrl;

    /**
     * The Odoo database name.
     */
    private string $database;

    /**
     * Odoo username.
     */
    private string $username;

    /**
     * Odoo password.
     */
    private string $password;

    /**
     * OdooApiService constructor.
     *
     * Initializes the service with configuration values passed as arguments.
     *
     * @param  string  $baseUrl  The Odoo base URL.
     * @param  string  $database  The Odoo database name.
     * @param  string  $username  Odoo username.
     * @param  string  $password  Odoo password.
     *
     * @throws Exception If any configuration argument is empty.
     */
    public function __construct(
        string $baseUrl,
        string $database,
        string $username,
        string $password
    ) {
        $this->baseUrl = $baseUrl;
        $this->database = $database;
        $this->username = $username;
        $this->password = $password;

        if (
            empty($this->baseUrl) ||
            empty($this->database) ||
            empty($this->username) ||
            empty($this->password)
        ) {
            throw new Exception('Odoo API configuration is incomplete.');
        }
    }

    /**
     * Authenticates with Odoo by calling the "common.authenticate" method.
     *
     * @return int The authenticated Odoo user ID.
     *
     * @throws Exception If authentication fails.
     */
    private function authenticate(): int
    {
        $result = $this->call('common', 'authenticate', [
            $this->database,
            $this->username,
            $this->password,
            [],
        ]);

        if (! is_int($result)) {
            throw new Exception(
                'Odoo authentication failed: no valid user ID returned.'
            );
        }

        return $result;
    }

    /**
     * Executes a JSON-RPC call to Odoo.
     *
     * @param  string  $service  The Odoo service to call (e.g., "common" or "object").
     * @param  string  $method  The method to execute within the service.
     * @param  array  $args  The parameters to pass to the method.
     * @return mixed The decoded JSON result from Odoo.
     *
     * @throws Exception If the API call fails or Odoo returns an error.
     */
    private function call(
        string $service,
        string $method,
        array $args = []
    ): mixed {
        $payload = [
            'jsonrpc' => '2.0',
            'method' => 'call',
            'params' => compact('service', 'method', 'args'),
            'id' => uniqid(),
        ];

        try {
            $response = Http::withOptions(['verify' => false])
                ->baseUrl($this->baseUrl)
                ->timeout(30)
                ->acceptJson()
                ->post('/jsonrpc', $payload);

            $responseBody = $response->json();

            if ($response->failed() || isset($responseBody['error'])) {
                throw new OdooResponseException(
                    'Odoo API Error: '.
                      ($responseBody['error']['message'] ?? 'Unknown error')
                );
            }

            return $responseBody['result'] ?? null;
        } catch (ConnectionException $e) {
            throw new OdooConnectionException(
                'Failed to connect to Odoo API: '.$e->getMessage()
            );
        } catch (RequestException $e) {
            throw new OdooRequestException(
                'Odoo API request failed: '.$e->getMessage()
            );
        }
    }

    /**
     * Performs a search and read operation on any Odoo model.
     *
     * @param  string  $model  The Odoo model to query (e.g., "hr.employee").
     * @param  array  $domain  The domain filters for the search.
     * @param  array  $fields  The fields to retrieve.
     * @return Collection The resulting records as a Laravel collection.
     *
     * @throws Exception If the API call fails.
     */
    private function searchRead(
        string $model,
        array $domain = [],
        array $fields = []
    ): Collection {
        $result = $this->call('object', 'execute_kw', [
            $this->database,
            $this->authenticate(),
            $this->password,
            $model,
            'search_read',
            [$domain],
            ['fields' => $fields],
        ]);

        return collect($result);
    }

    /**
     * Retrieves "hr.employee" records from Odoo.
     */
    public function getUsers(array $domain = []): Collection
    {
        return $this->searchRead('hr.employee', $domain, [
            'id',
            'name',
            'work_email',
            'tz',
            'active',
            'department_id',
            'category_ids',
            'resource_calendar_id',
        ]);
    }

    /**
     * Retrieves "hr.department" records from Odoo.
     */
    public function getDepartments(array $domain = []): Collection
    {
        return $this->searchRead('hr.department', $domain, [
            'id',
            'name',
            'active',
            'manager_id',
            'parent_id',
        ]);
    }

    /**
     * Retrieves "hr.employee.category" records from Odoo.
     */
    public function getCategories(array $domain = []): Collection
    {
        return $this->searchRead('hr.employee.category', $domain, [
            'id',
            'name',
            'active',
        ]);
    }

    /**
     * Retrieves "hr.leave.type" records from Odoo.
     */
    public function getLeaveTypes(array $domain = []): Collection
    {
        return $this->searchRead('hr.leave.type', $domain, [
            'id',
            'name',
            'active',
            'allocation_type',
            'validation_type',
            'request_unit',
            'unpaid',
        ]);
    }

    /**
     * Retrieves "hr.leave" records from Odoo, optionally filtered by date range.
     *
     * @param  string|null  $startDate  Local date in 'Y-m-d' format (e.g., '2025-01-13')
     * @param  string|null  $endDate  Local date in 'Y-m-d' format (e.g., '2025-01-13')
     * @param  array  $domain  Additional domain filters
     */
    public function getLeaves(
        ?string $startDate = null,
        ?string $endDate = null,
        array $domain = []
    ): Collection {
        $baseFilters = [
            ['state', 'in', ['validate', 'validate1', 'refuse', 'cancel', 'draft', 'confirm']],
            ['holiday_type', 'in', ['employee', 'category', 'department']],
        ];

        if ($startDate && $endDate) {
            // Overlap condition in Odoo domain format:
            // date_from <= range_end AND date_to >= range_start
            $baseFilters[] = ['date_from', '<=', $endDate.' 23:59:59'];
            $baseFilters[] = ['date_to', '>=', $startDate.' 00:00:00'];
        }

        return $this->searchRead('hr.leave', array_merge($baseFilters, $domain), [
            'id',
            'holiday_type',
            'employee_id',
            'category_id',
            'department_id',
            'date_from',
            'date_to',
            'number_of_days',
            'state',
            'holiday_status_id',
            'request_date_from', // Additional field for local date
            'request_date_to', // Additional field for local date
            'request_hour_from', // For half-day morning/afternoon
            'request_hour_to', // For half-day morning/afternoon
        ]);
    }

    /**
     * Retrieves "resource.calendar" records from Odoo.
     */
    public function getSchedules(array $domain = []): Collection
    {
        $fields = ['id', 'name', 'hours_per_day', 'tz'];

        return $this->searchRead('resource.calendar', $domain, $fields);
    }

    /**
     * Retrieves schedule details (attendances) from Odoo.
     */
    public function getScheduleDetails(array $domain = []): Collection
    {
        return $this->searchRead('resource.calendar.attendance', $domain, [
            'id',
            'calendar_id',
            'name',
            'dayofweek',
            'hour_from',
            'hour_to',
            'day_period', // Ensure this field is fetched if used
        ]);
    }

    /**
     * Retrieves version information from Odoo.
     */
    public function getServerVersion(): mixed
    {
        try {
            $result = $this->call('common', 'version');

            return [
                'success' => true,
                'message' => 'Odoo API is reachable.',
                'version' => $result['server_version'] ?? 'Unknown',
            ];
        } catch (OdooConnectionException|OdooRequestException|OdooResponseException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Implements Pingable::ping().
     * Pings the Odoo server by calling the "common.version" method.
     */
    public function ping(): array
    {
        try {
            $result = $this->call('common', 'version');

            return [
                'success' => true,
                'message' => 'Odoo API is reachable.',
                'version' => $result['server_version'] ?? 'Unknown',
            ];
        } catch (OdooConnectionException|OdooRequestException|OdooResponseException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
