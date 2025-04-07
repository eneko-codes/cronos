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
   * Executes a JSON GET call to ProofHub, handling pagination.
   *
   * @param string $endpoint The API endpoint (relative to base URL).
   * @param array  $params   Optional query parameters.
   * @return Collection A collection containing results from all pages.
   * @throws Exception On request failure.
   */
  private function call(string $endpoint, array $params = []): Collection
  {
    $currentPage = 1;
    $allResults = collect();
    $totalPages = 1; // Assume 1 page initially

    do {
      // Add/update the page parameter for the current request
      $params['page'] = $currentPage;
      $url = "{$this->baseUrl}{$endpoint}";

      try {
        $response = Http::withHeaders([
          'X-API-KEY' => $this->apiKey,
          'Accept' => 'application/json',
        ])
          ->timeout(60) // Increased timeout for potentially longer calls
          ->get($url, $params);

        if ($response->failed()) {
          throw new Exception(
            "ProofHub API call to {$endpoint} (Page {$currentPage}) failed: {$response->status()}"
          );
        }

        // Get the total number of pages from the header on the first request
        if ($currentPage === 1) {
          // Header names can be case-insensitive, normalize to lower
          $totalPagesHeader = strtolower($response->header('pages-count'));
          $totalPages = !empty($totalPagesHeader) ? (int) $totalPagesHeader : 1;
          Log::channel('sync')->debug(
            "ProofHub API call to {$endpoint}: Found {$totalPages} total pages."
          );
        }

        // Decode the JSON body
        $responseData = $response->json();

        // Ensure response data is an array (or can be treated as one)
        if (!is_array($responseData)) {
          Log::channel('sync')->warning(
            "ProofHub API call to {$endpoint} (Page {$currentPage}): Response was not an array.",
            ['response_type' => gettype($responseData)]
          );
          // If it's not an array and not page 1, stop processing further pages for this endpoint
          if ($currentPage > 1) {
            break;
          }
          // If it's page 1 and not an array, maybe it's a single object response? Return it as a collection.
          return collect([$responseData]);
        }

        // Add results from the current page to the collection
        $allResults = $allResults->merge($responseData);

        $currentPage++;
      } catch (Exception $e) {
        // Log the specific error and re-throw to let the job handle failure/retries
        Log::channel('sync')->error(
          'ProofHub API call error during pagination',
          [
            'endpoint' => $endpoint,
            'page' => $currentPage,
            'error' => $e->getMessage(),
          ]
        );
        throw $e;
      }
    } while ($currentPage <= $totalPages);

    return $allResults;
  }

  /**
   * Retrieve all users from ProofHub.
   *
   * @return Collection A collection of users.
   * @throws Exception On API call failure.
   */
  public function getUsers(): Collection
  {
    return $this->call('people');
  }

  /**
   * Retrieve all time entries from ProofHub with optional filtering.
   *
   * Endpoint: GET /alltime
   *
   * @param array $params Optional query parameters (e.g., ['from_date' => 'YYYY-MM-DD', 'to_date' => 'YYYY-MM-DD']).
   *                      The 'page' parameter will be handled by the call() method.
   * @return Collection Collection of time entries.
   * @throws Exception On API call failure.
   */
  public function getAllTime(array $params = []): Collection
  {
    return $this->call('alltime', $params);
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
    return $this->call('projects');
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
    return $this->call('alltodo');
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
      // We do a GET on 'people' page 1 as a simple "ping"
      // We don't need the full paginated result here.
      $url = "{$this->baseUrl}people";
      $response = Http::withHeaders([
        'X-API-KEY' => $this->apiKey,
        'Accept' => 'application/json',
      ])
        ->timeout(15) // Shorter timeout for ping
        ->get($url, ['page' => 1]); // Explicitly request page 1

      if ($response->failed()) {
        throw new Exception("ProofHub API ping failed: {$response->status()}");
      }

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
