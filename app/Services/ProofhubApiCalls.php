<?php

namespace App\Services;

use App\Contracts\Pingable;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Class ProofhubApiCalls
 *
 * Manages interactions with the ProofHub API by providing methods
 * for fetching data from various endpoints.
 *
 * IMPORTANT: This service only retrieves data; it does not perform calculations.
 *
 * @package App\Services
 */
class ProofhubApiCalls implements Pingable
{
  /**
   * @var string Base URL of the ProofHub API.
   */
  private string $baseUrl;

  /**
   * @var string API key for ProofHub.
   */
  private string $apiKey;

  /**
   * ProofhubApiCalls constructor.
   *
   * Initializes the service by constructing the base URL using the company URL
   * and setting up the API key.
   *
   * @throws Exception If ProofHub configuration is incomplete.
   */
  public function __construct()
  {
    $companyUrl = config('services.proofhub.company_url');
    $this->apiKey = config('services.proofhub.api_key');

    if (empty($companyUrl) || empty($this->apiKey)) {
      throw new Exception(
        'ProofHub API configuration is incomplete. Please check your config/services.php and .env files.'
      );
    }

    // Construct the base URL using the company URL
    $this->baseUrl = "https://{$companyUrl}.proofhub.com/api/v3/";
  }

  /**
   * Executes a JSON GET call to ProofHub.
   *
   * @param string $endpoint The API endpoint (relative to base URL).
   * @param array  $params   Optional query parameters.
   * @return mixed JSON-decoded response.
   * @throws Exception On request failure.
   */
  private function call(string $endpoint, array $params = []): mixed
  {
    $url = "{$this->baseUrl}{$endpoint}";
    try {
      $response = Http::withHeaders([
        'X-API-KEY' => $this->apiKey,
        'Accept' => 'application/json',
      ])
        ->timeout(30)
        ->get($url, $params);

      if ($response->failed()) {
        throw new Exception(
          "ProofHub API returned error: {$response->status()}"
        );
      }

      // Get response data without storing it
      $responseData = $response->json();

      return $responseData;
    } catch (Exception $e) {
      // Only throw the exception without logging
      // This prevents double logging when the caller also logs the exception
      throw $e;
    }
  }

  /**
   * Retrieve all users from ProofHub.
   *
   * @return Collection A collection of users.
   * @throws Exception On API call failure.
   */
  public function getUsers(): Collection
  {
    $result = $this->call('people');
    return collect($result);
  }

  /**
   * Retrieve all time entries from ProofHub with optional filtering.
   *
   * Endpoint: GET /alltime
   *
   * @param array $params Optional query parameters (e.g., ['from_date' => 'YYYY-MM-DD', 'to_date' => 'YYYY-MM-DD']).
   * @return array Array of time entries.
   * @throws Exception On API call failure.
   */
  public function getAllTime(array $params = []): array
  {
    $response = $this->call('alltime', $params);

    if (!is_array($response)) {
      Log::channel('sync')->error(
        'ProofHub alltime endpoint did not return an array.'
      );
      return [];
    }

    return $response;
  }

  /**
   * Retrieve all projects from ProofHub.
   *
   * Endpoint: GET /projects
   *
   * @return Collection A collection of projects.
   * @throws Exception On API call failure.
   */
  public function getProjects(): Collection
  {
    $result = $this->call('projects');
    return collect($result);
  }

  /**
   * Retrieve all tasks from ProofHub.
   *
   * Endpoint: GET /alltodo
   *
   * @return Collection A collection of tasks.
   * @throws Exception On API call failure.
   */
  public function getTasks(): Collection
  {
    $result = $this->call('alltodo');
    return collect($result);
  }

  /**
   * Implements Pingable::ping().
   * Ping ProofHub API to check connectivity.
   *
   * @return array Associative array indicating success status and message.
   */
  public function ping(): array
  {
    try {
      // We do a GET on 'people' as a simple "ping"
      $this->call('people');
      return [
        'success' => true,
        'message' => 'ProofHub API is reachable.',
      ];
    } catch (Exception $e) {
      return [
        'success' => false,
        'message' => 'Failed to connect to ProofHub API: ' . $e->getMessage(),
      ];
    }
  }
}
