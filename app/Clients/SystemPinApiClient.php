<?php

declare(strict_types=1);

namespace App\Clients;

use App\DataTransferObjects\SystemPin\SystemPinAttendanceDTO;
use App\DataTransferObjects\SystemPin\SystemPinUserDTO;
use App\Exceptions\ApiConnectionException;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Handles all communication with the SystemPin API, including authentication, health checks, and data retrieval.
 */
class SystemPinApiClient
{
    /**
     * The base URL for the SystemPin API.
     */
    protected string $baseUrl;

    /**
     * The API key for authenticating requests to SystemPin.
     */
    protected string $apiKey;

    /**
     * Constructs a new SystemPinApiClient instance.
     *
     * @param  string  $baseUrl  The base URL for the SystemPin API.
     * @param  string  $apiKey  The API key for SystemPin.
     *
     * @throws ApiConnectionException If any configuration argument is empty.
     */
    public function __construct(string $baseUrl, string $apiKey)
    {
        if (empty($baseUrl) || empty($apiKey)) {
            throw new ApiConnectionException('SystemPin API configuration is incomplete.');
        }

        $this->baseUrl = $baseUrl;
        $this->apiKey = $apiKey;
    }

    /**
     * Makes an HTTP GET request to a SystemPin API endpoint with Bearer token authentication.
     *
     * @param  string  $endpoint  API endpoint relative to base URL.
     * @param  array  $params  Query parameters to include in the request.
     * @return array Decoded JSON response.
     *
     * @throws ApiConnectionException If the API request fails.
     */
    private function call(string $endpoint, array $params = []): array
    {
        try {
            $response = Http::systempin()
                ->get($endpoint, $params);

            if ($response->failed()) {
                throw new ApiConnectionException(
                    "SystemPin API returned error: {$response->status()}"
                );
            }

            Log::debug('SystemPin API Response', [
                'endpoint' => $endpoint,
                'params' => $params,
                'response' => $response->json(),
            ]);

            return $response->json();
        } catch (Exception $e) {
            throw new ApiConnectionException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Retrieves all employees with email addresses from SystemPin.
     *
     * @return Collection|SystemPinUserDTO[] Collection of SystemPinUserDTOs.
     *
     * @throws ApiConnectionException If the API request fails.
     */
    public function getAllEmployees(): Collection
    {
        try {
            $data = $this->call('/GetDataFromDataBase', [
                'QueryID' => '13', // Query ID for getting employees with email
            ]);

            if (! isset($data['data']) || ! is_array($data['data'])) {
                Log::warning('SystemPin API returned unexpected employee data structure', ['data' => $data]);

                return collect();
            }

            return collect($data['data'])
                ->map(fn ($employee) => new SystemPinUserDTO(
                    $employee['id'] ?? null,
                    $employee['Nombre'] ?? null,
                    $employee['Email'] ?? null
                ))
                ->filter(fn ($dto) => ! empty($dto->Email))
                ->values();
        } catch (ApiConnectionException $e) {
            Log::error('Failed to fetch SystemPin employees', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Retrieves attendance data from SystemPin for a specific date range.
     *
     * @param  string  $fromDate  Start date in 'Y-m-d' format.
     * @param  string  $toDate  End date in 'Y-m-d' format.
     * @return Collection|SystemPinAttendanceDTO[] Collection of SystemPinAttendanceDTOs.
     *
     * @throws ApiConnectionException If the API request fails.
     */
    public function getAttendanceData(string $fromDate, string $toDate): Collection
    {
        try {
            // Convert dates to SystemPin format (YYYYMMDD)
            $systemPinFromDate = Carbon::parse($fromDate)->format('Ymd');
            $systemPinToDate = Carbon::parse($toDate)->format('Ymd');

            $data = $this->call('/GetDataFromPresenciaPin', [
                'QueryID' => '1', // Query ID for presence data
                'EmployeeFilter' => '*', // Get all employees
                'DateFrom' => $systemPinFromDate,
                'DateTo' => $systemPinToDate,
            ]);

            if (empty($data)) {
                Log::warning('SystemPin API returned empty attendance data', ['data' => $data]);

                return collect();
            }

            return collect($data)
                ->map(fn ($attendance) => new SystemPinAttendanceDTO(
                    $attendance['EmployeeID'] ?? null,
                    $attendance['InternalEmployeeID'] ?? null,
                    $attendance['Date'] ?? null,
                    $attendance['TimeRecords'] ?? [],
                    $attendance['Schedule'] ?? null,
                    $attendance['TimeOff'] ?? null,
                    $attendance['TimeOffHours'] ?? []
                ))
                ->values();
        } catch (ApiConnectionException $e) {
            Log::error('Failed to fetch SystemPin attendance data', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Checks connectivity to the SystemPin API by performing a lightweight GET request to the health endpoint.
     *
     * @return array Associative array containing the success status and a message.
     */
    public function ping(): array
    {
        try {
            // Use a simple query to check connectivity
            $this->call('/GetDataFromDataBase', ['QueryID' => '13']);

            return [
                'success' => true,
                'message' => 'Successfully connected to SystemPin API.',
            ];
        } catch (ApiConnectionException $e) {
            return [
                'success' => false,
                'message' => 'SystemPin API ping failed: '.$e->getMessage(),
            ];
        }
    }
}
