<?php

declare(strict_types=1);

namespace App\Clients;

use App\Contracts\Pingable;
use App\Exceptions\ApiConnectionException;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Handles all communication with the DeskTime API, including authentication, data retrieval, and health checks.
 * Provides methods to fetch users, attendance data, and account timezone, and to check API health.
 */
class DesktimeApiClient implements Pingable
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

            return $response->json();
        } catch (\Exception $e) {
            throw new ApiConnectionException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Retrieves all users from DeskTime for a given date and period.
     *
     * @param  string|null  $date  Date in 'Y-m-d' format. Defaults to today.
     * @param  string  $period  Either 'day' or 'month'. Defaults to 'month'.
     * @return Collection Collection of employees data by date.
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

        return collect($data['employees']);
    }

    /**
     * Retrieves attendance data for a single employee for a given date.
     *
     * @param  int  $userId  DeskTime user ID.
     * @param  string|null  $date  Date in 'Y-m-d' format. Defaults to today.
     * @return Collection Employee data including attendance.
     *
     * @throws ApiConnectionException If the API request fails.
     */
    public function getSingleEmployee(
        int $userId,
        ?string $date = null
    ): Collection {
        $date = $date ?? Carbon::today()->toDateString();

        $data = $this->call('/employee', [
            'id' => $userId,
            'date' => $date,
        ]);

        return collect($data);
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
