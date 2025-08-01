<?php

declare(strict_types=1);

namespace App\Clients;

use App\DataTransferObjects\Desktime\DesktimeAttendanceDTO;
use App\DataTransferObjects\Desktime\DesktimeEmployeeDTO;
use App\Exceptions\ApiConnectionException;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Handles all communication with the DeskTime API, including authentication, data retrieval, and health checks.
 * Provides methods to fetch users, attendance data, and account timezone, and to check API health.
 */
class DesktimeApiClient
{
    /**
     * The base URL for the DeskTime API.
     */
    private string $baseUrl;

    /**
     * The API key for authenticating requests to DeskTime.
     */
    private string $apiKey;

    /**
     * The account timezone, cached after first retrieval.
     */
    private ?string $accountTimezone = null;

    /**
     * Constructs a new DesktimeApiClient instance.
     *
     * @param  string  $baseUrl  The base URL for the DeskTime API.
     * @param  string  $apiKey  The API key for DeskTime.
     *
     * @throws ApiConnectionException If configuration arguments are empty or the API request fails.
     */
    public function __construct(string $baseUrl, string $apiKey)
    {
        $this->baseUrl = $baseUrl;
        $this->apiKey = $apiKey;

        if (empty($this->baseUrl) || empty($this->apiKey)) {
            throw new ApiConnectionException('DeskTime API configuration is incomplete.');
        }
    }

    /**
     * Makes an HTTP GET request to a DeskTime API endpoint with the API key.
     *
     * @param  string  $endpoint  API endpoint (e.g., '/ping', '/employees').
     * @param  array  $params  Query parameters to include in the request.
     * @return array Decoded JSON response.
     *
     * @throws ApiConnectionException If the API request fails.
     */
    private function call(string $endpoint, array $params = []): array
    {
        $params['apiKey'] = $this->apiKey;

        try {
            $response = Http::get("{$this->baseUrl}{$endpoint}", $params);

            if ($response->failed()) {
                throw new ApiConnectionException(
                    "DeskTime API returned error: {$response->status()}"
                );
            }

            Log::debug('DeskTime API Response', [
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
     * Retrieves all users from DeskTime for a given date and period.
     *
     * @param  string|null  $date  Date in 'Y-m-d' format. Defaults to today.
     * @param  string  $period  Either 'day' or 'month'. Defaults to 'month'.
     * @return Collection|DesktimeEmployeeDTO[] Collection of DesktimeEmployeeDTOs.
     *
     * @throws ApiConnectionException If the API request fails.
     */
    public function getAllEmployees(
        ?string $date = null,
        string $period = 'month'
    ): Collection {
        $date = $date ?? Carbon::today()->toDateString();

        $data = $this->call('/employees', [
            'date' => $date,
            'period' => $period,
        ]);

        if (! isset($data['employees'])) {
            return collect();
        }

        // Flatten all employees into a single collection of DesktimeEmployeeDTOs
        return collect($data['employees'])
            ->flatMap(fn ($dateUsers) => collect($dateUsers)->map(fn ($user) => new DesktimeEmployeeDTO(
                $user['id'] ?? null,
                $user['email'] ?? null,
                $user['name'] ?? null,
                $user['groupId'] ?? null,
                $user['group'] ?? null,
                $user['profileUrl'] ?? null,
                $user['isOnline'] ?? null,
                $user['arrived'] ?? null,
                $user['left'] ?? null,
                $user['late'] ?? null,
                $user['onlineTime'] ?? null,
                $user['offlineTime'] ?? null,
                $user['desktimeTime'] ?? null,
                $user['atWorkTime'] ?? null,
                $user['afterWorkTime'] ?? null,
                $user['beforeWorkTime'] ?? null,
                $user['productiveTime'] ?? null,
                $user['productivity'] ?? null,
                $user['efficiency'] ?? null,
                $user['work_starts'] === false ? null : $user['work_starts'] ?? null,
                $user['work_ends'] === false ? null : $user['work_ends'] ?? null,
                $user['notes'] ?? null,
                $user['activeProject'] ?? null,
                $user['apps'] ?? null,
                $user['projects'] ?? null
            )))
            ->values();
    }

    /**
     * Retrieves the account timezone from DeskTime, caching the result after the first call.
     *
     * @return string Timezone identifier.
     *
     * @throws ApiConnectionException If the API request fails.
     */
    public function getAccountTimezone(): string
    {
        if ($this->accountTimezone === null) {
            $data = $this->call('/company');
            $this->accountTimezone = $data['timezone_identifier'] ?? 'UTC';
        }

        return $this->accountTimezone;
    }

    /**
     * Retrieves all attendance records for all employees for a specific date.
     *
     * @param  string  $date  Date in 'Y-m-d' format.
     * @return Collection<int, DesktimeAttendanceDTO> Collection keyed by user ID.
     */
    public function getAllAttendanceForDate(string $date): Collection
    {
        $data = $this->call('/employees', [
            'date' => $date,
            'period' => 'day',
        ]);

        $attendance = collect();
        if (! isset($data['employees'][$date]) || ! is_array($data['employees'][$date])) {
            return $attendance;
        }

        foreach ($data['employees'][$date] as $user) {
            $dto = new DesktimeAttendanceDTO(
                $user['id'] ?? null,
                $user['name'] ?? null,
                $user['email'] ?? null,
                $user['groupId'] ?? null,
                $user['group'] ?? null,
                $user['profileUrl'] ?? null,
                $user['isOnline'] ?? null,
                $user['arrived'] === false ? null : $user['arrived'] ?? null,
                $user['left'] === false ? null : $user['left'] ?? null,
                $user['late'] ?? null,
                $user['onlineTime'] ?? null,
                $user['offlineTime'] ?? null,
                $user['desktimeTime'] ?? null,
                $user['atWorkTime'] ?? null,
                $user['afterWorkTime'] ?? null,
                $user['beforeWorkTime'] ?? null,
                $user['productiveTime'] ?? null,
                $user['productivity'] ?? null,
                $user['efficiency'] ?? null,
                $user['work_starts'] === false ? null : $user['work_starts'] ?? null,
                $user['work_ends'] === false ? null : $user['work_ends'] ?? null,
                $user['notes'] ?? null,
                $user['activeProject'] ?? null,
                $user['apps'] ?? null,
                $user['projects'] ?? null,
                $date
            );
            $attendance[$user['id']] = $dto;
        }

        return $attendance;
    }

    /**
     * Checks the health of the DeskTime API by performing a lightweight GET request.
     *
     * @return array Associative array indicating success status and a message.
     */
    public function ping(): array
    {
        try {
            $data = $this->call('/ping');

            return [
                'success' => isset($data['pong']),
                'message' => isset($data['pong'])
                  ? 'DeskTime API is reachable.'
                  : 'DeskTime API returned unexpected response.',
            ];
        } catch (ApiConnectionException $e) {
            return [
                'success' => false,
                'message' => 'Failed to connect to DeskTime API: '.$e->getMessage(),
            ];
        }
    }
}
