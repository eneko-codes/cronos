<?php

declare(strict_types=1);

namespace App\Clients;

use App\Contracts\Pingable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SystemPinApiClient implements Pingable
{
    protected string $baseUrl;

    protected string $apiKey;

    /**
     * SystemPinApiClient constructor.
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
     * @return array An array containing the success status and a message.
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
        } catch (\Exception $e) {
            Log::error('SystemPin API ping failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'SystemPin API ping failed: '.$e->getMessage(),
            ];
        }
    }

    // Add other SystemPin API methods here (e.g., getUsers, getAttendances)
}
