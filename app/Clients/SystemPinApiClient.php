<?php

declare(strict_types=1);

namespace App\Clients;

use App\Contracts\Pingable;
use App\Exceptions\ApiConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Handles all communication with the SystemPin API, including authentication, health checks, and (future) data retrieval.
 * Provides a method to check API connectivity and is designed for easy extension with additional API methods.
 */
class SystemPinApiClient implements Pingable
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
     * @param  string|null  $baseUrl  The base URL for the SystemPin API.
     * @param  string|null  $apiKey  The API key for SystemPin.
     */
    public function __construct(?string $baseUrl, ?string $apiKey)
    {
        $this->baseUrl = $baseUrl ?? '';
        $this->apiKey = $apiKey ?? '';

        if (! $this->baseUrl || ! $this->apiKey) {
            Log::warning('SystemPin API URL or Key is not configured.');
        }
    }

    /**
     * Checks connectivity to the SystemPin API by performing a lightweight GET request to the health endpoint.
     *
     * @return array Associative array containing the success status and a message.
     */
    public function ping(): array
    {
        if (! $this->baseUrl || ! $this->apiKey) {
            return [
                'success' => false,
                'message' => 'SystemPin API URL or Key is not configured.',
            ];
        }

        try {
            // TODO: Adjust the endpoint and expected response based on SystemPin API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
            ])->get($this->baseUrl.'/health'); // Example endpoint

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Successfully connected to SystemPin API.',
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to connect to SystemPin API. Status: '.$response->status(),
            ];
        } catch (ApiConnectionException $e) {
            return [
                'success' => false,
                'message' => 'SystemPin API ping failed: '.$e->getMessage(),
            ];
        }
    }

    // Add other SystemPin API methods here (e.g., getUsers, getAttendances)
}
