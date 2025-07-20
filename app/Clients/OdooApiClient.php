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
        // Ensure all required parameters are provided
        if (
            empty($baseUrl) ||
            empty($database) ||
            empty($username) ||
            empty($password)
        ) {
            throw new ApiConnectionException('Odoo API configuration is incomplete.');
        }

        $this->baseUrl = $baseUrl;
        $this->database = $database;
        $this->username = $username;
        $this->password = $password;
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
            $item['id'],
            $this->extractStringOrNull($item, 'work_email'),
            $item['name'],
            $this->extractStringOrNull($item, 'tz'),
            (bool) ($item['active'] ?? true),
            $this->extractRelationArray($item, 'department_id'),
            is_array($item['category_ids']) ? $item['category_ids'] : [],
            $this->extractRelationArray($item, 'resource_calendar_id'),
            $this->extractStringOrNull($item, 'job_title'),
            $this->extractRelationArray($item, 'parent_id')
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
            $item['id'],
            $item['name'],
            (bool) ($item['active'] ?? true),
            $this->extractRelationArray($item, 'manager_id'),
            $this->extractRelationArray($item, 'parent_id')
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
            $item['id'],
            $item['name'],
            (bool) ($item['active'] ?? true)
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
            $item['id'],
            $item['name'],
            $this->extractStringOrNull($item, 'request_unit'),
            (bool) ($item['active'] ?? true),
            $this->extractStringOrNull($item, 'create_date'),
            $this->extractStringOrNull($item, 'write_date')
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
            $item['id'],
            $item['holiday_type'],
            $item['date_from'],
            $item['date_to'],
            $this->extractFloatOrNull($item, 'number_of_days'),
            $item['state'],
            $this->extractRelationArray($item, 'holiday_status_id'),
            $this->extractFloatOrNull($item, 'request_hour_from'),
            $this->extractFloatOrNull($item, 'request_hour_to'),
            $this->extractRelationArray($item, 'employee_id'),
            $this->extractRelationArray($item, 'category_id'),
            $this->extractRelationArray($item, 'department_id')
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
            $item['id'],
            $item['name'],
            (bool) ($item['active'] ?? true),
            is_array($item['attendance_ids']) ? $item['attendance_ids'] : [],
            $this->extractFloatOrNull($item, 'hours_per_day'),
            $this->extractBooleanOrNull($item, 'two_weeks_calendar'),
            $this->extractStringOrNull($item, 'two_weeks_explanation'),
            $this->extractBooleanOrNull($item, 'flexible_hours'),
            $this->extractStringOrNull($item, 'create_date'),
            $this->extractStringOrNull($item, 'write_date')
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
            $item['id'],
            $this->extractRelationArray($item, 'calendar_id'),
            $this->extractStringOrNull($item, 'name'),
            $item['dayofweek'],
            $this->extractFloatOrNull($item, 'hour_from'),
            $this->extractFloatOrNull($item, 'hour_to'),
            $this->extractStringOrNull($item, 'day_period'),
            $this->extractIntOrNull($item, 'week_type'),
            $this->extractStringOrNull($item, 'date_from'),
            $this->extractStringOrNull($item, 'date_to'),
            (bool) ($item['active'] ?? true),
            $this->extractStringOrNull($item, 'create_date'),
            $this->extractStringOrNull($item, 'write_date')
        ));
    }

    /**
     * Helper method to extract float values from Odoo API response, handling false values.
     */
    private function extractFloatOrNull(array $item, string $key): ?float
    {
        $value = $item[$key] ?? null;

        return ($value !== false && is_numeric($value)) ? (float) $value : null;
    }

    /**
     * Helper method to extract integer values from Odoo API response, handling false values.
     */
    private function extractIntOrNull(array $item, string $key): ?int
    {
        $value = $item[$key] ?? null;

        return ($value !== false && is_numeric($value)) ? (int) $value : null;
    }

    /**
     * Helper method to extract string values from Odoo API response, handling false values.
     */
    private function extractStringOrNull(array $item, string $key): ?string
    {
        $value = $item[$key] ?? null;

        return ($value !== false && $value !== null) ? (string) $value : null;
    }

    /**
     * Helper method to extract boolean values from Odoo API response, handling false values.
     */
    private function extractBooleanOrNull(array $item, string $key): ?bool
    {
        $value = $item[$key] ?? null;

        return ($value !== null) ? (bool) $value : null;
    }

    /**
     * Helper method to extract relation arrays (Many2one fields) from Odoo API response.
     * Many2one fields return [id, name] arrays or false.
     */
    private function extractRelationArray(array $item, string $key): ?array
    {
        $value = $item[$key] ?? null;

        return (is_array($value) && count($value) >= 2) ? $value : null;
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
