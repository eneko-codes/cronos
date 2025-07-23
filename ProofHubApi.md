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
- **Response Example (from official docs):**
  ```json
  [
    {
      "id": 12009183,
      "first_name": "Chris",
      "last_name": "Wagley",
      "title": "Manager",
      "email": "chris@email.com",
      "role": {
        "id": 903912753
      },
      "groups": [33838231, 33838232],
      "timezone": 8,
      "initials": "N",
      "image_url": null,
      "profile_color": "#781f1f",
      "language": "en",
      "suspended": false,
      "send_invite": true,
      "last_active": "2016-09-19T06:17:22+00:00",
      "created_at": "2016-09-16T10:39:25+00:00",
      "updated_at": "2016-09-16T10:39:25+00:00",
      "projects": [23423233, 23423234]
    }
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
- **Response Example (from official docs):**
  ```json
  [
    {
      "id": 23423233,
      "title": "PH Marketing",
      "description": "Project description goes here",
      "archived": false,
      "status": 12345678,
      "color": "#41236D",
      "start_date": "2016-12-10",
      "end_date": "2016-12-15",
      "template": false,
      "sample_project": false,
      "favourite": false,
      "favourite_sort": null,
      "category": {
        "id": 65707070
      },
      "creator": {
        "id": 11765082
      },
      "assigned": [12009183, 11679192],
      "manager": {
        "id": 11679192
      },
      "created_at": "2016-06-24T12:18:26+00:00",
      "modified_at": "2016-06-24T12:18:26+00:00"
    }
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
- **Response Example (from official docs):**
  ```json
  [
    {
      "ticket": "20842",
      "id": 985356917305,
      "title": "Task 1",
      "description": "Task description",
      "start_date": null,
      "due_date": null,
      "estimated_hours": null,
      "estimated_mins": null,
      "logged_hours": null,
      "logged_mins": null,
      "updated_at": "2021-03-22T13:13:46+00:00",
      "created_at": "2021-03-22T13:13:46+00:00",
      "completed": false,
      "assigned": [8172598588],
      "labels": [4766001983],
      "sub_tasks": 0,
      "rrule": null,
      "task_history": null,
      "percent_progress": 0,
      "attachments": [],
      "comments": 0,
      "by_me": true,
      "template": false,
      "form_task": false,
      "timesheet_id": null,
      "user_stages": [],
      "project": {
        "id": 4469983073,
        "name": "Castle"
      },
      "creator": {
        "id": 7279827447
      },
      "list": {
        "id": 169808767259,
        "name": "Test Task List Alpha"
      },
      "workflow": {
        "id": 2747398168,
        "name": "Basic workflow"
      },
      "stage": {
        "id": 6074498296,
        "name": "New"
      },
      "custom_fields": []
    }
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
- **Response Example (from official docs):**
  ```json
  [
    {
      "id": 80870831,
      "status": "billable",
      "description": "Brainstorm session with potential users",
      "date": "2016-10-05",
      "created_at": "2016-10-05T08:19:01+00:00",
      "logged_hours": 2,
      "logged_mins": 30,
      "timer": false,
      "today": 1475625600,
      "sort": 0,
      "by_me": true,
      "project": {
        "id": 23423233,
        "name": "PH Marketing"
      },
      "creator": {
        "id": 12009183
      },
      "task": null,
      "timesheet": {
        "id": 23570135,
        "title": "Prepare training material"
      }
    }
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

- [ProofHub API v3 Docs](https://github.com/ProofHub/api_v3)

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
    // The v3 API uses the 'Link' header for pagination, not the count of the response.
    $nextPageUrl = parseNextLinkFromHeader($response->header('Link'));
} while ($nextPageUrl);
```
