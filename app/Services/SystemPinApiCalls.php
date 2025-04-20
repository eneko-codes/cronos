<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SystemPinApiCalls
{
  protected string $baseUrl;
  protected string $apiKey;

  public function __construct()
  {
    // TODO: Replace with actual configuration keys if they exist
    $this->baseUrl = config('services.systempin.url');
    $this->apiKey = config('services.systempin.key');

    if (!$this->baseUrl || !$this->apiKey) {
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
    if (!$this->baseUrl || !$this->apiKey) {
      return false;
    }

    try {
      // TODO: Adjust the endpoint and expected response based on SystemPin API
      $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . $this->apiKey,
      ])->get($this->baseUrl . '/health'); // Example endpoint

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
