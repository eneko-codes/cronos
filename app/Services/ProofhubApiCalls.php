<?php

namespace App\Services;

use App\Contracts\Pingable;
use Exception;
use Illuminate\Http\Client\Response;
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
     * Executes a JSON GET call to a specific ProofHub page (or URL).
     *
     * Returns the data collection and pagination details for that single page.
     *
     * @param  string  $url  The full URL to call (can be base + endpoint or a 'next' URL from Link header).
     * @param  array  $params  Query parameters for the request (used if $url doesn't contain them).
     * @param  string|null  $endpointName  Optional: Name of the original endpoint for logging purposes.
     * @return array{data: Collection, totalPages: int|null, nextPageUrl: string|null}
     *                                                                                 'data'        => Collection of items from the response.
     *                                                                                 'totalPages'  => Total pages from 'pages-count' header (null if header not found or Link header used).
     *                                                                                 'nextPageUrl' => URL for the next page from 'Link' header (null if not found).
     *
     * @throws Exception On request failure or unexpected response format.
     */
    public function callPage(
        string $url,
        array $params = [],
        ?string $endpointName = null
    ): array {
        try {
            Log::debug('ProofHub API Page Request:', [
                'url' => $url,
                'params' => $params,
                'endpoint' => $endpointName, // Log original endpoint if provided
            ]);

            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'Accept' => 'application/json',
            ])
                ->timeout(60) // Consider making this configurable
                ->get($url, $params);

            if ($response->failed()) {
                throw new Exception(
                    "ProofHub API call to {$url} failed: {$response->status()}"
                );
            }

            // Decode the JSON body
            $responseData = $response->json();

            // --- Pagination Details ---
            $nextPageUrl = $this->parseNextLinkFromHeader($response->header('Link'));
            $totalPages = null;

            // Only read pages-count if Link header didn't provide a next URL
            if ($nextPageUrl === null) {
                $totalPagesHeader = strtolower($response->header('pages-count'));
                if ($endpointName === 'alltime') {
                    $totalPages = null; // Signal to the job that fallback count is unreliable
                    Log::warning(
                        'ProofHub API Warning: No Link header for /alltime. Fallback pagination is unreliable and will be skipped.',
                        [
                            'url' => $url,
                            'params' => $params,
                            'pages-count_header' => $totalPagesHeader ?? 'N/A',
                        ]
                    );
                } else {
                    // For other endpoints, trust pages-count as a fallback
                    $totalPages = ! empty($totalPagesHeader) ? (int) $totalPagesHeader : 1;
                    Log::debug(
                        'ProofHub API Page Response: No Link header found, using fallback.',
                        [
                            'url' => $url,
                            'endpoint' => $endpointName,
                            'pages-count' => $totalPagesHeader ?? 'N/A',
                            'total_pages_detected' => $totalPages,
                        ]
                    );
                }
            } else {
                Log::debug('ProofHub API Page Response: Found Link header.', [
                    'url' => $url,
                    'endpoint' => $endpointName,
                    'next_page_url' => $nextPageUrl,
                ]);
            }

            // --- Process Response Data ---
            $dataCollection = collect();
            if (is_array($responseData)) {
                $dataCollection = collect($responseData);
            } elseif ($responseData !== null) {
                // Handle single non-null, non-array item
                Log::info(
                    'ProofHub API call to {$url}: Response was not an array, wrapping single item.',
                    ['response_type' => gettype($responseData)]
                );
                $dataCollection = collect([$responseData]);
            } else {
                // Response was null or empty, return empty collection
                Log::debug(
                    'ProofHub API call to {$url}: Response data was null or empty.',
                    ['response_type' => gettype($responseData)]
                );
            }

            return [
                'data' => $dataCollection,
                'totalPages' => $totalPages,
                'nextPageUrl' => $nextPageUrl,
            ];
        } catch (Exception $e) {
            Log::error('ProofHub API call error', [
                'url' => $url,
                'params' => $params,
                'endpoint' => $endpointName,
                'error' => $e->getMessage(),
            ]);
            throw $e; // Re-throw to allow job failure/retry logic
        }
    }

    /**
     * Parses the 'Link' HTTP header to find the URL for the 'next' relation.
     *
     * Example Link header:
     * '<https://company.proofhub.com/api/v3/people?page=2>; rel="next", <https://company.proofhub.com/api/v3/people?page=10>; rel="last"'
     *
     * @param  string|null  $linkHeader  The value of the Link header.
     * @return string|null The URL for the next page, or null if not found.
     */
    private function parseNextLinkFromHeader(?string $linkHeader): ?string
    {
        if (empty($linkHeader)) {
            return null;
        }

        // Split the header into individual link parts (separated by commas)
        $links = explode(',', $linkHeader);

        foreach ($links as $link) {
            // Split each part into URL and parameters (separated by semicolon)
            $segments = explode(';', trim($link));

            // Check if there are at least 2 segments (URL and rel parameter)
            if (count($segments) < 2) {
                continue;
            }

            // Extract the URL (remove surrounding '<' and '>')
            $url = trim($segments[0]);
            if (str_starts_with($url, '<') && str_ends_with($url, '>')) {
                $url = substr($url, 1, -1);
            } else {
                continue; // Malformed URL part
            }

            // Check the relation parameter
            for ($i = 1; $i < count($segments); $i++) {
                $param = trim($segments[$i]);
                // Look for rel="next" (case-insensitive check for 'next')
                if (preg_match('/rel\s*=\s*"?next"?/i', $param)) {
                    return $url; // Found the next link
                }
            }
        }

        return null; // No 'next' link found
    }

    /**
     * Retrieve all users from ProofHub.
     * DEPRECATED: Jobs should now call callPage directly in a loop.
     * This method remains temporarily for compatibility but will be removed.
     *
     * @return Collection A collection of users.
     *
     * @throws Exception On API call failure.
     */
    public function getUsers(): Collection
    {
        Log::warning('Deprecated method ProofhubApiCalls::getUsers() called.');

        return $this->fetchAllPages('people');
    }

    /**
     * Retrieve all time entries from ProofHub with optional filtering.
     * DEPRECATED: Jobs should now call callPage directly in a loop.
     * This method remains temporarily for compatibility but will be removed.
     *
     * Endpoint: GET /alltime
     *
     * @param  array  $params  Optional query parameters (e.g., ['from_date' => 'YYYY-MM-DD', 'to_date' => 'YYYY-MM-DD']).
     * @return Collection Collection of time entries.
     *
     * @throws Exception On API call failure.
     */
    public function getAllTime(array $params = []): Collection
    {
        Log::warning('Deprecated method ProofhubApiCalls::getAllTime() called.');

        return $this->fetchAllPages('alltime', $params);
    }

    /**
     * Retrieve all projects from ProofHub.
     * DEPRECATED: Jobs should now call callPage directly in a loop.
     * This method remains temporarily for compatibility but will be removed.
     *
     * Endpoint: GET /projects
     *
     * @return Collection A collection of projects.
     *
     * @throws Exception On API call failure.
     */
    public function getProjects(): Collection
    {
        Log::warning('Deprecated method ProofhubApiCalls::getProjects() called.');

        return $this->fetchAllPages('projects');
    }

    /**
     * Retrieve all tasks from ProofHub.
     * DEPRECATED: Jobs should now call callPage directly in a loop.
     * This method remains temporarily for compatibility but will be removed.
     *
     * Endpoint: GET /alltodo
     *
     * @return Collection A collection of tasks.
     *
     * @throws Exception On API call failure.
     */
    public function getTasks(): Collection
    {
        Log::warning('Deprecated method ProofhubApiCalls::getTasks() called.');

        return $this->fetchAllPages('alltodo');
    }

    /**
     * Internal helper to fetch all pages (temporary for deprecated methods).
     *
     * @throws Exception
     */
    private function fetchAllPages(
        string $endpoint,
        array $params = []
    ): Collection {
        $allResults = collect();
        $currentPage = 1;
        $totalPages = 1; // Assume 1 page initially for fallback
        $nextPageUrl = null;

        $currentUrl = "{$this->baseUrl}{$endpoint}"; // Initial URL

        do {
            $urlToCall = $nextPageUrl ?: $currentUrl;
            $paramsToCall = [];
            if ($nextPageUrl === null && $currentPage === 1) {
                // First request using fallback
                $paramsToCall = $params;
                $paramsToCall['page'] = $currentPage;
            } elseif ($nextPageUrl === null) {
                // Subsequent fallback
                $paramsToCall = ['page' => $currentPage];
                $urlToCall = "{$this->baseUrl}{$endpoint}"; // Reset URL for fallback
            }

            $pageResult = $this->callPage($urlToCall, $paramsToCall, $endpoint);

            $allResults = $allResults->merge($pageResult['data']);

            $nextPageUrl = $pageResult['nextPageUrl'];

            if ($nextPageUrl === null) {
                // Using fallback
                if ($currentPage === 1) {
                    // Get total pages only on first fallback request
                    $totalPages = $pageResult['totalPages'] ?? 1;
                }
                if ($currentPage < $totalPages) {
                    $currentPage++;
                } else {
                    $currentPage = null; // Signal stop
                }
            } else {
                // Using Link header, reset fallback vars
                $currentPage = null;
                $totalPages = null;
            }
        } while ($nextPageUrl !== null || $currentPage !== null);

        return $allResults;
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
                'message' => 'Failed to connect to ProofHub API: '.$e->getMessage(),
            ];
        }
    }
}
