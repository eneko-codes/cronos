# ProofHub API Documentation

## Overview

This document describes how the ProofHub API is used in this application, including authentication, endpoints, parameters, and best practices for integration. It is intended for developers working on the sync and integration features.

---

## Authentication

- **Type:** API Key
- **How:** Pass the API key in the `X-API-KEY` header for every request.
- **Example:**
  ```http
  GET https://yourcompany.proofhub.com/api/v3/people
  X-API-KEY: YOUR_API_KEY
  ```

---

## Endpoints Used

### 1. Get All Users

- **Endpoint:** `/people`
- **Method:** GET
- **Headers:**
  - `X-API-KEY`: Your ProofHub API key
- **Parameters:**
  - `page` (integer, optional): For pagination.
- **Example Request:**
  ```http
  GET https://yourcompany.proofhub.com/api/v3/people?page=1
  X-API-KEY: YOUR_API_KEY
  ```
- **Response:**
  ```json
  [
    {
      "id": 123,
      "name": "Jane Doe",
      "email": "jane@example.com",
      ...
    },
    ...
  ]
  ```

### 2. Get All Projects

- **Endpoint:** `/projects`
- **Method:** GET
- **Headers:**
  - `X-API-KEY`: Your ProofHub API key
- **Parameters:**
  - `page` (integer, optional): For pagination.
- **Example Request:**
  ```http
  GET https://yourcompany.proofhub.com/api/v3/projects?page=1
  X-API-KEY: YOUR_API_KEY
  ```
- **Response:**
  ```json
  [
    {
      "id": 456,
      "name": "Project X",
      ...
    },
    ...
  ]
  ```

### 3. Get All Tasks

- **Endpoint:** `/alltodo`
- **Method:** GET
- **Headers:**
  - `X-API-KEY`: Your ProofHub API key
- **Parameters:**
  - `page` (integer, optional): For pagination.
- **Example Request:**
  ```http
  GET https://yourcompany.proofhub.com/api/v3/alltodo?page=1
  X-API-KEY: YOUR_API_KEY
  ```
- **Response:**
  ```json
  [
    {
      "id": 789,
      "name": "Task 1",
      "project_id": 456,
      ...
    },
    ...
  ]
  ```

### 4. Get All Time Entries

- **Endpoint:** `/alltime`
- **Method:** GET
- **Headers:**
  - `X-API-KEY`: Your ProofHub API key
- **Parameters:**
  - `from_date` (string, required): Start date in `YYYY-MM-DD` format.
  - `to_date` (string, required): End date in `YYYY-MM-DD` format.
  - `page` (integer, optional): For pagination.
- **Example Request:**
  ```http
  GET https://yourcompany.proofhub.com/api/v3/alltime?from_date=2024-07-01&to_date=2024-07-07&page=1
  X-API-KEY: YOUR_API_KEY
  ```
- **Response:**
  ```json
  [
    {
      "id": 1011,
      "user_id": 123,
      "project_id": 456,
      "task_id": 789,
      "duration": 3600,
      "date": "2024-07-01",
      ...
    },
    ...
  ]
  ```

---

## Best Practices

- Always check for API errors and handle them gracefully.
- Use pagination to fetch all data (loop through pages until no more results).
- Use `from_date` and `to_date` to fetch historical time entries in bulk.
- Store and use ProofHub user/project/task IDs for mapping.
- Use HTTPS for all requests.
- Keep your API key secret and never expose it in client-side code.

---

## Caveats

- API rate limits may apply (check ProofHub docs for details).
- Data availability depends on user activity and ProofHub account configuration.
- Some endpoints may return large result sets; always use pagination.
- API key should be kept secret and never exposed in client-side code.

---

## References

- [ProofHub API Docs](https://www.proofhub.com/api)

---

## Example Sync Loop (PHP)

```php
$page = 1;
do {
    $response = Http::withHeaders([
        'X-API-KEY' => $apiKey,
    ])->get('https://yourcompany.proofhub.com/api/v3/alltime', [
        'from_date' => $from,
        'to_date' => $to,
        'page' => $page,
    ]);
    // Process $response
    $page++;
} while (count($response) > 0);
```
