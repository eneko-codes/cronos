<?php

declare(strict_types=1);

namespace App\Clients;

use App\DataTransferObjects\Odoo\OdooCategoryDTO;
use App\DataTransferObjects\Odoo\OdooDepartmentDTO;
use App\DataTransferObjects\Odoo\OdooLeaveDTO;
use App\DataTransferObjects\Odoo\OdooLeaveTypeDTO;
use App\DataTransferObjects\Odoo\OdooScheduleDetailDTO;
use App\DataTransferObjects\Odoo\OdooScheduleDTO;
use App\DataTransferObjects\Odoo\OdooUserDTO;
use App\Exceptions\ApiConnectionException;
use App\Exceptions\ApiRequestException;
use App\Exceptions\ApiResponseException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Handles all interactions with the Odoo API, including authentication, data retrieval, and health checks.
 * Provides methods to fetch users, departments, categories, leave types, leaves, schedules, and schedule details.
 * All data is returned as raw as possible, without renaming keys or setting defaults.
 *
 * Note: Odoo 13 stores 'date_from' and 'date_to' in 'hr.leave' as UTC datetime fields.
 */
class OdooApiClient
{
    /**
     * The base URL for the Odoo API (e.g., https://odoo.company.com).
     */
    private string $baseUrl;

    /**
     * The Odoo database name.
     */
    private string $database;

    /**
     * The Odoo username for authentication.
     */
    private string $username;

    /**
     * The Odoo password for authentication.
     */
    private string $password;

    /**
     * Constructs a new OdooApiClient instance.
     *
     * Initializes the client with the required Odoo connection parameters.
     * Throws an exception if any parameter is missing.
     *
     * @param  string  $baseUrl  The Odoo base URL.
     * @param  string  $database  The Odoo database name.
     * @param  string  $username  The Odoo username.
     * @param  string  $password  The Odoo password.
     *
     * @throws ApiConnectionException If any configuration argument is empty.
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

        // Ensure all required parameters are provided
        if (
            empty($this->baseUrl) ||
            empty($this->database) ||
            empty($this->username) ||
            empty($this->password)
        ) {
            throw new ApiConnectionException('Odoo API configuration is incomplete.');
        }
    }

    /**
     * Authenticates with Odoo by calling the 'common.authenticate' method.
     *
     * Returns the authenticated Odoo user ID if successful.
     *
     * @return int The authenticated Odoo user ID.
     *
     * @throws ApiConnectionException If authentication fails.
     */
    private function authenticate(): int
    {
        $result = $this->call('common', 'authenticate', [
            $this->database,
            $this->username,
            $this->password,
            [],
        ]);

        // Odoo returns the user ID on success
        if (! is_int($result)) {
            throw new ApiConnectionException(
                'Odoo authentication failed: no valid user ID returned.'
            );
        }

        return $result;
    }

    /**
     * Executes a JSON-RPC call to the Odoo API.
     *
     * Handles all low-level HTTP and error handling for Odoo API requests.
     *
     * @param  string  $service  The Odoo service to call (e.g., 'common' or 'object').
     * @param  string  $method  The method to execute within the service.
     * @param  array  $args  The parameters to pass to the method.
     * @return mixed The decoded JSON result from Odoo.
     *
     * @throws ApiConnectionException|ApiRequestException|ApiResponseException If the API call fails or Odoo returns an error.
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

            Log::debug('Odoo API Raw Response', [
                'service' => $service,
                'method' => $method,
                'args' => $args,
                'response' => $responseBody,
            ]);

            // Check for HTTP or Odoo-level errors
            if ($response->failed() || isset($responseBody['error'])) {
                throw new ApiResponseException(
                    'Odoo API Error: '.
                      ($responseBody['error']['message'] ?? 'Unknown error')
                );
            }

            return $responseBody['result'] ?? null;
        } catch (ConnectionException $e) {
            throw new ApiConnectionException(
                'Failed to connect to Odoo API: '.$e->getMessage()
            );
        } catch (RequestException $e) {
            throw new ApiRequestException(
                'Odoo API request failed: '.$e->getMessage()
            );
        }
    }

    /**
     * Performs a search and read operation on any Odoo model.
     *
     * This is a generic helper for all Odoo model queries.
     *
     * @param  string  $model  The Odoo model to query (e.g., 'hr.employee').
     * @param  array  $domain  The domain filters for the search.
     * @param  array  $fields  The fields to retrieve.
     * @return Collection The resulting records as a Laravel collection.
     *
     * @throws ApiConnectionException|ApiRequestException|ApiResponseException If the API call fails.
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
     * Retrieves all 'hr.employee' records from Odoo.
     *
     * Maps each record to an OdooUserDTO.
     *
     * @param  array  $domain  Optional domain filters.
     * @return \Illuminate\Support\Collection|OdooUserDTO[] Collection of OdooUserDTOs.
     */
    public function getUsers(array $domain = []): Collection
    {
        return $this->searchRead('hr.employee', $domain, [
            'id',
            'work_email',
            'name',
            'tz',
            'active',
            'department_id',
            'category_ids',
            'resource_calendar_id',
            'job_title',
            'parent_id',
        ])->map(fn ($item) => new OdooUserDTO(
            Arr::get($item, 'id', null),
            Arr::get($item, 'work_email', null) !== false ? Arr::get($item, 'work_email', null) : null,
            Arr::get($item, 'name', null) !== false ? Arr::get($item, 'name', null) : null,
            Arr::get($item, 'tz', null) !== false ? Arr::get($item, 'tz', null) : null,
            Arr::get($item, 'active', null),
            Arr::get($item, 'department_id', null) !== false ? Arr::get($item, 'department_id', null) : null,
            Arr::get($item, 'category_ids', null) !== false ? Arr::get($item, 'category_ids', null) : [],
            Arr::get($item, 'resource_calendar_id', null) !== false ? Arr::get($item, 'resource_calendar_id', null) : null,
            Arr::get($item, 'job_title', null) !== false ? Arr::get($item, 'job_title', null) : null,
            Arr::get($item, 'parent_id', null) !== false ? Arr::get($item, 'parent_id', null) : null
        ));
    }

    /**
     * Retrieves all 'hr.department' records from Odoo.
     *
     * Maps each record to an OdooDepartmentDTO.
     *
     * @param  array  $domain  Optional domain filters.
     * @return \Illuminate\Support\Collection|OdooDepartmentDTO[] Collection of OdooDepartmentDTOs.
     */
    public function getDepartments(array $domain = []): Collection
    {
        return $this->searchRead('hr.department', $domain, [
            'id',
            'name',
            'active',
            'manager_id',
            'parent_id',
        ])->map(fn ($item) => new OdooDepartmentDTO(
            Arr::get($item, 'id', null),
            Arr::get($item, 'name', null) !== false ? Arr::get($item, 'name', null) : null,
            Arr::get($item, 'active', null),
            Arr::get($item, 'manager_id', null) !== false ? Arr::get($item, 'manager_id', null) : null,
            Arr::get($item, 'parent_id', null) !== false ? Arr::get($item, 'parent_id', null) : null
        ));
    }

    /**
     * Retrieves all 'hr.employee.category' records from Odoo.
     *
     * Maps each record to an OdooCategoryDTO.
     *
     * @param  array  $domain  Optional domain filters.
     * @return \Illuminate\Support\Collection|OdooCategoryDTO[] Collection of OdooCategoryDTOs.
     */
    public function getCategories(array $domain = []): Collection
    {
        return $this->searchRead('hr.employee.category', $domain, [
            'id',
            'name',
            'active',
        ])->map(fn ($item) => new OdooCategoryDTO(
            Arr::get($item, 'id', null),
            Arr::get($item, 'name', null) !== false ? Arr::get($item, 'name', null) : null,
            Arr::get($item, 'active', null)
        ));
    }

    /**
     * Retrieves all 'hr.leave.type' records from Odoo.
     *
     * Maps each record to an OdooLeaveTypeDTO.
     *
     * @param  array  $domain  Optional domain filters.
     * @return \Illuminate\Support\Collection|OdooLeaveTypeDTO[] Collection of OdooLeaveTypeDTOs.
     */
    public function getLeaveTypes(array $domain = []): Collection
    {
        return $this->searchRead('hr.leave.type', $domain, [
            'id',
            'name',
            'request_unit',
            'active',
            'create_date',
            'write_date',
        ])->map(fn ($item) => new OdooLeaveTypeDTO(
            Arr::get($item, 'id', null),
            Arr::get($item, 'name', null) !== false ? Arr::get($item, 'name', null) : null,
            Arr::get($item, 'request_unit', null) !== false ? Arr::get($item, 'request_unit', null) : null,
            Arr::get($item, 'active', null),
            Arr::get($item, 'create_date', null) !== false ? Arr::get($item, 'create_date', null) : null,
            Arr::get($item, 'write_date', null) !== false ? Arr::get($item, 'write_date', null) : null
        ));
    }

    /**
     * Retrieves 'hr.leave' records from Odoo, optionally filtered by date range and additional domain filters.
     *
     * Maps each record to an OdooLeaveDTO. Handles conversion of nested/array fields and type casting.
     *
     * @param  string|null  $startDate  Optional start date in 'Y-m-d' format.
     * @param  string|null  $endDate  Optional end date in 'Y-m-d' format.
     * @param  array  $domain  Additional domain filters.
     * @return \Illuminate\Support\Collection|OdooLeaveDTO[] Collection of OdooLeaveDTOs.
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

        // If a date range is provided, add date filters
        if ($startDate && $endDate) {
            $baseFilters[] = ['date_from', '<=', $endDate.' 23:59:59'];
            $baseFilters[] = ['date_to', '>=', $startDate.' 00:00:00'];
        }

        return $this->searchRead('hr.leave', array_merge($baseFilters, $domain), [
            'id',
            'holiday_type',
            'date_from',
            'date_to',
            'employee_id',
            'holiday_status_id',
            'state',
            'number_of_days',
            'category_id',
            'department_id',
            'request_hour_from',
            'request_hour_to',
        ])->map(fn ($item) => new OdooLeaveDTO(
            Arr::get($item, 'id', null),
            Arr::get($item, 'holiday_type', null) !== false ? Arr::get($item, 'holiday_type', null) : null,
            Arr::get($item, 'date_from', null) !== false ? Arr::get($item, 'date_from', null) : null,
            Arr::get($item, 'date_to', null) !== false ? Arr::get($item, 'date_to', null) : null,
            Arr::get($item, 'number_of_days', null) !== false ? Arr::get($item, 'number_of_days', null) : null,
            Arr::get($item, 'state', null) !== false ? Arr::get($item, 'state', null) : null,
            Arr::get($item, 'holiday_status_id', null) !== false ? Arr::get($item, 'holiday_status_id', null) : null,
            Arr::get($item, 'request_hour_from', null) !== false ? Arr::get($item, 'request_hour_from', null) : null,
            Arr::get($item, 'request_hour_to', null) !== false ? Arr::get($item, 'request_hour_to', null) : null,
            Arr::get($item, 'employee_id', null) !== false ? Arr::get($item, 'employee_id', null) : null,
            Arr::get($item, 'category_id', null) !== false ? Arr::get($item, 'category_id', null) : null,
            Arr::get($item, 'department_id', null) !== false ? Arr::get($item, 'department_id', null) : null
        ));
    }

    /**
     * Retrieves all 'resource.calendar' records from Odoo.
     *
     * Maps each record to an OdooScheduleDTO.
     *
     * @param  array  $domain  Optional domain filters.
     * @return \Illuminate\Support\Collection|OdooScheduleDTO[] Collection of OdooScheduleDTOs.
     */
    public function getSchedules(array $domain = []): Collection
    {
        return $this->searchRead('resource.calendar', $domain, [
            'id',
            'name',
            'active',
            'attendance_ids',
            'hours_per_day',
            'two_weeks_calendar',
            'two_weeks_explanation',
            'flexible_hours',
            'create_date',
            'write_date',
        ])->map(fn ($item) => new OdooScheduleDTO(
            Arr::get($item, 'id', null),
            Arr::get($item, 'name', null) !== false ? Arr::get($item, 'name', null) : null,
            Arr::get($item, 'active', null),
            Arr::get($item, 'attendance_ids', null) !== false ? Arr::get($item, 'attendance_ids', null) : [],
            Arr::get($item, 'hours_per_day', null) !== false ? Arr::get($item, 'hours_per_day', null) : null,
            Arr::get($item, 'two_weeks_calendar', null) !== false ? Arr::get($item, 'two_weeks_calendar', null) : null,
            Arr::get($item, 'two_weeks_explanation', null) !== false ? Arr::get($item, 'two_weeks_explanation', null) : null,
            Arr::get($item, 'flexible_hours', null),
            Arr::get($item, 'create_date', null) !== false ? Arr::get($item, 'create_date', null) : null,
            Arr::get($item, 'write_date', null) !== false ? Arr::get($item, 'write_date', null) : null
        ));
    }

    /**
     * Retrieves schedule details from Odoo.
     *
     * Maps each record to an OdooScheduleDetailDTO. Handles conversion of nested/array fields and type casting.
     *
     * @param  array  $domain  Optional domain filters.
     * @return \Illuminate\Support\Collection|OdooScheduleDetailDTO[] Collection of OdooScheduleDetailDTOs.
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
            'day_period',
            'week_type',
            'date_from',
            'date_to',
            'active',
            'create_date',
            'write_date',
        ])->map(fn ($item) => new OdooScheduleDetailDTO(
            Arr::get($item, 'id', null),
            Arr::get($item, 'calendar_id', null) !== false ? Arr::get($item, 'calendar_id', null) : null,
            Arr::get($item, 'name', null) !== false ? Arr::get($item, 'name', null) : null,
            Arr::get($item, 'dayofweek', null) !== false ? Arr::get($item, 'dayofweek', null) : null,
            Arr::get($item, 'hour_from', null) !== false ? Arr::get($item, 'hour_from', null) : null,
            Arr::get($item, 'hour_to', null) !== false ? Arr::get($item, 'hour_to', null) : null,
            Arr::get($item, 'day_period', null) !== false ? Arr::get($item, 'day_period', null) : null,
            Arr::get($item, 'week_type', null) !== false ? Arr::get($item, 'week_type', null) : null,
            Arr::get($item, 'date_from', null) !== false ? Arr::get($item, 'date_from', null) : null,
            Arr::get($item, 'date_to', null) !== false ? Arr::get($item, 'date_to', null) : null,
            Arr::get($item, 'active', null),
            Arr::get($item, 'create_date', null) !== false ? Arr::get($item, 'create_date', null) : null,
            Arr::get($item, 'write_date', null) !== false ? Arr::get($item, 'write_date', null) : null
        ));
    }

    /**
     * Retrieves version information from the Odoo server.
     *
     * Calls the 'common.version' method and returns version info if available.
     *
     * @return array Associative array with success status, message, and version info if available.
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
        } catch (ApiConnectionException|ApiRequestException|ApiResponseException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Checks the health of the Odoo API by calling the 'common.version' method.
     *
     * Returns a success status and message if the API is reachable.
     *
     * @return array Associative array indicating success status and a message.
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
        } catch (ApiConnectionException|ApiRequestException|ApiResponseException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
