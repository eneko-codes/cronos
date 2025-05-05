<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SystemPinApiService
{
    protected string $baseUrl;

    protected string $apiKey;

    /**
     * SystemPinApiService constructor.
     *
     * Initializes the service with the base URL and API key.
     *
     * @param  string|null  $baseUrl  The base URL for the SystemPin API.
     * @param  string|null  $apiKey  The API key for authenticating with SystemPin.
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
     * Ping the SystemPin API endpoint to check connectivity.
     *
     * @return bool True if the connection is successful, false otherwise.
     */
    public function ping(): bool
    {
        if (! $this->baseUrl || ! $this->apiKey) {
            return false;
        }

        try {
            // TODO: Adjust the endpoint and expected response based on SystemPin API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
            ])->get($this->baseUrl.'/health'); // Example endpoint

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('SystemPin API ping failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    // Add other SystemPin API methods here (e.g., getUsers, getAttendances)
}
