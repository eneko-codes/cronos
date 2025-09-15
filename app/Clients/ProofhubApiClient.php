<?php

declare(strict_types=1);

namespace App\Clients;

use App\DataTransferObjects\Proofhub\ProofhubProjectDTO;
use App\DataTransferObjects\Proofhub\ProofhubTaskDTO;
use App\DataTransferObjects\Proofhub\ProofhubTimeEntryDTO;
use App\DataTransferObjects\Proofhub\ProofhubUserDTO;
use App\Exceptions\ApiConnectionException;
use App\Exceptions\ApiRequestException;
use App\Exceptions\ApiResponseException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Handles all communication with the ProofHub API, including authentication, data retrieval, and pagination.
 * Provides methods to fetch users, projects, tasks, and time entries, and to check API health.
 */
class ProofhubApiClient
{
    /**
     * The base URL for the ProofHub API (e.g., https://company.proofhub.com/api/v3/).
     */
    private string $baseUrl;

    /**
     * The API key used for authenticating requests to ProofHub.
     */
    private string $apiKey;

    /**
     * Constructs a new ProofhubApiClient instance.
     *
     * @param  string  $companyUrl  The company-specific part of the ProofHub URL (e.g., 'yourcompany').
     * @param  string  $apiKey  The API key for ProofHub.
     *
     * @throws ApiConnectionException If configuration is incomplete.
     */
    public function __construct(string $companyUrl, string $apiKey)
    {
        if (empty($companyUrl) || empty($apiKey)) {
            throw new ApiConnectionException(
                'ProofHub API configuration is incomplete. Please check your config/services.php and .env files.'
            );
        }

        $this->apiKey = $apiKey;
        // Construct the base URL using the company URL and API v3
        $this->baseUrl = "https://{$companyUrl}.proofhub.com/api/v3/";
    }

    /**
     * Executes a GET request to a ProofHub API endpoint, returning a collection of results and pagination metadata for the requested page.
     * Handles error logging and response validation.
     *
     * @param  string  $url  The full endpoint URL or a pagination link from the API response.
     * @param  array  $params  Query parameters for the request.
     * @param  string|null  $endpointName  Optional: Name of the endpoint for logging.
     * @return array{data: Collection, totalPages: int|null, nextPageUrl: string|null} Contains the data collection, total pages, and next page URL if available.
     *
     * @throws ApiConnectionException|ApiRequestException|ApiResponseException On request failure or unexpected response format.
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
                'User-Agent' => 'CronosApp',
            ])
                ->timeout(60) // Consider making this configurable
                ->get($url, $params);

            Log::debug('ProofHub API Response', [
                'url' => $url,
                'params' => $params,
                'endpoint' => $endpointName,
                'response' => $response->json(),
            ]);

            if ($response->failed()) {
                throw new ApiConnectionException(
                    "ProofHub API call to {$url} failed: {$response->status()}"
                );
            }

            // Decode the JSON body
            $responseData = $response->json();

            // --- Pagination Details ---
            $nextPageUrl = $this->parseNextLinkFromHeader($response->header('Link'));
            $totalPages = null;

            if ($nextPageUrl !== null) {
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
                Log::info(
                    'ProofHub API call to {$url}: Response was not an array, wrapping single item.',
                    ['response_type' => gettype($responseData)]
                );
                $dataCollection = collect([$responseData]);
            } else {
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
        } catch (ApiConnectionException $e) {
            throw $e;
        } catch (ApiRequestException $e) {
            throw $e;
        } catch (ApiResponseException $e) {
            throw $e;
        }
    }

    /**
     * Parses the 'Link' HTTP header to extract the URL for the 'next' page in paginated responses.
     *
     * Example Link header:
     *   <https://company.proofhub.com/api/v3/people?page=2>; rel="next", <https://company.proofhub.com/api/v3/people?page=10>; rel="last"
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
                if (preg_match('/rel\s*=\s*" ?next" ?/i', $param)) {
                    return $url; // Found the next link
                }
            }
        }

        return null; // No 'next' link found
    }

    /**
     * Retrieves all users from ProofHub, handling pagination to return a complete collection.
     * Endpoint: GET /people
     *
     * @return Collection All users as a Laravel collection.
     *
     * @throws ApiConnectionException|ApiRequestException|ApiResponseException On request failure.
     */
    public function getUsers(): Collection
    {
        $allResults = collect();
        $url = $this->baseUrl.'people';

        do {
            $pageResult = $this->callPage($url, [], 'people');
            $allResults = $allResults->merge($pageResult['data']->map(function ($item) {
                return new ProofhubUserDTO(
                    id: $item['id'] ?? null,
                    email: $item['email'] ?? null,
                    first_name: $item['first_name'] ?? null,
                    last_name: $item['last_name'] ?? null,
                    verified: $item['verified'] ?? null,
                    groups: $item['groups'] ?? null,
                    suspended: $item['suspended'] ?? null,
                    role: $item['role'] ?? null,
                    proofhub_created_at: $item['created_at'] ?? null,
                    proofhub_updated_at: $item['updated_at'] ?? null
                );
            }));
            $url = $pageResult['nextPageUrl'];
        } while ($url && $pageResult['data']->isNotEmpty());

        return $allResults;
    }

    /**
     * Retrieves all projects from ProofHub, handling pagination to return a complete collection.
     * Endpoint: GET /projects
     *
     * @return Collection All projects as a Laravel collection.
     *
     * @throws ApiConnectionException|ApiRequestException|ApiResponseException On request failure.
     */
    public function getProjects(): Collection
    {
        $allResults = collect();
        $url = $this->baseUrl.'projects';

        do {
            $pageResult = $this->callPage($url, [], 'projects');
            $allResults = $allResults->merge($pageResult['data']->map(function ($item) {
                return new ProofhubProjectDTO(
                    id: $item['id'] ?? null,
                    title: $item['title'] ?? null,
                    assigned: $item['assigned'] ?? null,
                    status: $item['status'] ?? null,
                    description: $item['description'] ?? null,
                    created_at: $item['created_at'] ?? null,
                    updated_at: $item['updated_at'] ?? null,
                    creator: $item['creator'] ?? null,
                    manager: $item['manager'] ?? null,
                    proofhub_created_at: $item['created_at'] ?? null,
                    proofhub_updated_at: $item['updated_at'] ?? null
                );
            }));
            $url = $pageResult['nextPageUrl'];
        } while ($url && $pageResult['data']->isNotEmpty());

        return $allResults;
    }

    /**
     * Retrieves all tasks from ProofHub, handling pagination to return a complete collection.
     * Endpoint: GET /alltodo
     *
     * @return Collection All tasks as a Laravel collection.
     *
     * @throws ApiConnectionException|ApiRequestException|ApiResponseException On request failure.
     */
    public function getTasks(): Collection
    {
        $allResults = collect();
        $url = $this->baseUrl.'alltodo';

        do {
            $pageResult = $this->callPage($url, [], 'alltodo');
            $allResults = $allResults->merge($pageResult['data']->map(function ($item) {
                return new ProofhubTaskDTO(
                    id: $item['id'] ?? null,
                    title: $item['title'] ?? null,
                    project_id: $item['project_id'] ?? null,
                    project: $item['project'] ?? null,
                    assigned: $item['assigned'] ?? null,
                    subtasks: $item['subtasks'] ?? null,
                    stage: $item['stage'] ?? null,
                    due_date: $item['due_date'] ?? null,
                    description: $item['description'] ?? null,
                    tags: $item['tags'] ?? null,
                    creator: $item['creator'] ?? null,
                    proofhub_created_at: $item['created_at'] ?? null,
                    proofhub_updated_at: $item['updated_at'] ?? null
                );
            }));
            $url = $pageResult['nextPageUrl'];
        } while ($url && $pageResult['data']->isNotEmpty());

        return $allResults;
    }

    /**
     * Retrieves all time entries from ProofHub, handling pagination to return a complete collection.
     * Endpoint: GET /alltime
     *
     * @param  array  $params  Optional query parameters (e.g., ['from_date' => 'YYYY-MM-DD', 'to_date' => 'YYYY-MM-DD'])
     * @return Collection All time entries as a Laravel collection.
     *
     * @throws ApiConnectionException|ApiRequestException|ApiResponseException On request failure.
     */
    public function getTimeEntries(array $params = []): Collection
    {
        $allResults = collect();
        $url = $this->baseUrl.'alltime';

        // ProofHub /alltime endpoint uses start/limit pagination, not Link headers
        $start = 0;
        $limit = 100; // Maximum allowed by ProofHub API

        do {
            // Add pagination parameters to the existing params
            $paginatedParams = array_merge($params, [
                'start' => $start,
                'limit' => $limit,
            ]);

            $pageResult = $this->callPage($url, $paginatedParams, 'alltime');
            $dataCount = $pageResult['data']->count();

            $allResults = $allResults->merge($pageResult['data']->map(function ($item) {
                return new ProofhubTimeEntryDTO(
                    id: $item['id'] ?? null,
                    date: $item['date'] ?? null,
                    created_at: $item['created_at'] ?? null,
                    logged_hours: $item['logged_hours'] ?? null,
                    logged_mins: $item['logged_mins'] ?? null,
                    timesheet: $item['timesheet'] ?? null,
                    task: $item['task'] ?? null,
                    project: $item['project'] ?? null,
                    creator: $item['creator'] ?? null,
                    status: $item['status'] ?? null,
                    description: $item['description'] ?? null,
                );
            }));

            $start += $limit;

            // Continue until we get less than the limit (indicating end of data)
        } while ($dataCount >= $limit);

        return $allResults;
    }

    /**
     * Checks connectivity to the ProofHub API by performing a lightweight GET request.
     *
     * @return array Associative array indicating success status and a message.
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
                'User-Agent' => 'CronosApp',
            ])
                ->timeout(15) // Shorter timeout for ping
                ->get($url, ['page' => 1]); // Explicitly request page 1

            if ($response->failed()) {
                throw new ApiConnectionException("ProofHub API ping failed: {$response->status()}");
            }

            return [
                'success' => true,
                'message' => 'ProofHub API is reachable.',
            ];
        } catch (ApiConnectionException $e) {
            return [
                'success' => false,
                'message' => 'Failed to connect to ProofHub API: '.$e->getMessage(),
            ];
        }
    }
}
