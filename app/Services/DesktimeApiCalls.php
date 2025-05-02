<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Pingable;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Class DesktimeApiCalls
 *
 * Handles API interactions with DeskTime.
 */
class DesktimeApiCalls implements Pingable
{
    /**
     * The base URL for the DeskTime API.
     */
    private string $baseUrl;

    /**
     * The API key for authenticating with DeskTime.
     */
    private string $apiKey;

    private ?string $accountTimezone = null;

    /**
     * DesktimeApiCalls constructor.
     *
     * Initializes the service with configuration settings.
     *
     * @throws Exception If configuration is missing.
     */
    public function __construct()
    {
        $this->baseUrl = config('services.desktime.base_url');
        $this->apiKey = config('services.desktime.api_key');

        if (empty($this->baseUrl) || empty($this->apiKey)) {
            throw new Exception('DeskTime API configuration is incomplete.');
        }
    }

    /**
     * Makes an HTTP GET request to a DeskTime API endpoint with the API key.
     *
     * @param  string  $endpoint  API endpoint (e.g., '/ping', '/employees')
     * @param  array  $params  Query parameters to include in the request
     * @return array Decoded JSON response
     *
     * @throws Exception If the API request fails.
     */
    private function call(string $endpoint, array $params = []): array
    {
        $params['apiKey'] = $this->apiKey;

        try {
            $response = Http::get("{$this->baseUrl}{$endpoint}", $params);

            if ($response->failed()) {
                throw new Exception(
                    "DeskTime API returned error: {$response->status()}"
                );
            }

            return $response->json();
        } catch (Exception $e) {
            // Only throw the exception without logging
            // This prevents double logging when the caller also logs the exception
            throw $e;
        }
    }

    /**
     * Retrieves all users from DeskTime.
     *
     * @param  string|null  $date  Date in 'Y-m-d' format. Defaults to today.
     * @param  string  $period  Either 'day' or 'month'. Defaults to 'month'.
     * @return Collection Collection of employees data by date
     *
     * @throws Exception If the API request fails.
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
     * Retrieves attendance data for a single employee.
     *
     * @param  int  $userId  DeskTime user ID.
     * @param  string|null  $date  Date in 'Y-m-d' format. Defaults to today.
     * @return Collection Employee data including attendance
     *
     * @throws Exception If the API request fails.
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
     * Gets the account timezone from DeskTime.
     *
     * @return string Timezone identifier
     *
     * @throws Exception If the API request fails
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
     * Implements Pingable::ping().
     * Checks the health of the DeskTime API.
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
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to connect to DeskTime API: '.$e->getMessage(),
            ];
        }
    }
}
