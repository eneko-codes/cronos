# DeskTime API Documentation

To use the DeskTime API, you'll need to use the unique API key that is connected to your DeskTime account. With this key, you'll be able to authenticate your requests and access the data you need.

This documentation provides all the necessary information to get started, including details on available endpoints, query parameters, and response formats. Whether you're a developer or simply looking to extend your use of DeskTime, we hope this documentation will be a valuable resource for you.

---

## Base URL

```
https://desktime.com/api/v2/{format}/{action}/?{params}
```

- Supported formats: `json`, `jsonp`, `plist`

---

## Authentication

All requests require your DeskTime API key as a query parameter:

```
apiKey={apiKey}
```

---

## Endpoints

### 1. Ping

Check if the API server is online.

**GET** `/api/v2/json/ping?apiKey={apiKey}`

**Response Example:**

```json
{
  "pong": "1678956844",
  "__request_time": "1678956844"
}
```

---

### 2. Company Info

Get information about your company account.

**GET** `/api/v2/json/company?apiKey={apiKey}`

**Response Example:**

```json
{
  "name": "Dunder Mifflin, Inc.",
  "work_starts": "09:00:00",
  "work_ends": "18:00:00",
  "work_duration": "28800",
  "working_days": 31,
  "work_start_tracking": "00:00:00",
  "work_stop_tracking": "23:59:59",
  "timezone_identifier": "Europe/London",
  "__request_time": "1678956861"
}
```

---

### 3. Get Single Employee Data (with Attendance)

Get tracking and attendance data for a single employee for a specific date.

**GET** `/api/v2/json/employee?apiKey={apiKey}&id={employeeId}&date={date}`

- `apiKey`: user api key (required)
- `id`: Employee ID (optional, defaults to current user)
- `date`: Date in `YYYY-MM-DD` format (optional, defaults to today)

**Response Example:**

```json
{
  "id": 1,
  "name": "Michael Scott",
  "email": "demo@desktime.com",
  "groupId": 1,
  "group": "Accounting",
  "profileUrl": "https://desktime.com/app/employee/1/2012-03-16",
  "isOnline": false,
  "arrived": false,
  "left": false,
  "late": false,
  "onlineTime": 0,
  "offlineTime": 0,
  "desktimeTime": 0,
  "atWorkTime": 0,
  "afterWorkTime": 0,
  "beforeWorkTime": 0,
  "productiveTime": 0,
  "productivity": 0,
  "efficiency": 0,
  "work_starts": "23:59:59",
  "work_ends": "00:00:00",
  "notes": {
    "Skype": "Find.me",
    "Slack": "MichielS"
  },
  "activeProject": [],
  "apps": {
    "0": {
      "chat.openai.com": {
        "id": 31409706221,
        "app": "chat.openai.com",
        "name": "chat.openai.com",
        "type": "web",
        "category_id": 0,
        "duration": 530
      }
    },
    "1": {
      "eslint.org": {
        "id": 31408589973,
        "app": "eslint.org",
        "name": "eslint.org",
        "type": "web",
        "category_id": 0,
        "duration": 110
      }
    },
    "-1": {
      "phpstorm": {
        "id": 31408096349,
        "app": "PhpStorm",
        "name": "PhpStorm",
        "type": "app",
        "category_id": 0,
        "duration": 6084
      }
    }
  },
  "projects": [
    {
      "project_id": 1,
      "project_title": "Calling Dwight",
      "task_id": 3802072,
      "task_title": "We need to talk!",
      "duration": 13186
    }
  ]
}
```

- **Field Types:**
  - `notes`: object
  - `activeProject`: array/object
  - `apps`: object
  - `projects`: array of objects

---

### 4. Get All Employees (with Attendance Data)

Get all employees and their attendance for a specific day or month.

**GET** `/api/v2/json/employees?apiKey={apiKey}&date={date}&period={period}`

- `apiKey`: user api key (required)
- `date`: Date in `YYYY-MM-DD` format (optional, defaults to today)
- `period`: `day` or `month` (optional, defaults to `day`)

**Response Example:**

```json
{
  "employees": {
    "2023-03-16": {
      "1": {
        "id": 1,
        "name": "Michael Scott",
        "email": "demo@desktime.com",
        "groupId": 1,
        "group": "Accounting",
        "profileUrl": "https://desktime.com/app/employee/1/2023-03-16",
        "isOnline": false,
        "arrived": false,
        "left": false,
        "late": false,
        "onlineTime": 0,
        "offlineTime": 0,
        "desktimeTime": 0,
        "atWorkTime": 0,
        "afterWorkTime": 0,
        "beforeWorkTime": 0,
        "productiveTime": 0,
        "productivity": 0,
        "efficiency": 0,
        "work_starts": "23:59:59",
        "work_ends": "00:00:00",
        "notes": {
          "Skype": "Find.me",
          "Slack": "MichielS"
        },
        "activeProject": []
      },
      "2": {
        "id": 2,
        "name": "Andy Bernard",
        "email": "demo3@desktime.com",
        "groupId": 106345,
        "group": "Marketing",
        "profileUrl": "https://desktime.com/app/employee/2/2023-03-16",
        "isOnline": true,
        "arrived": "2023-03-16 09:17:00",
        "left": "2023-03-16 10:58:00",
        "late": true,
        "onlineTime": 6027,
        "offlineTime": 0,
        "desktimeTime": 6027,
        "atWorkTime": 6060,
        "afterWorkTime": 0,
        "beforeWorkTime": 0,
        "productiveTime": 4213,
        "productivity": 69.9,
        "efficiency": 14.75,
        "work_starts": "09:00:00",
        "work_ends": "18:00:00",
        "notes": {
          "Background": "Law and accounting"
        },
        "activeProject": {
          "project_id": 67973,
          "project_title": "Blue Book",
          "task_id": 42282,
          "task_title": "Blue Book task",
          "duration": 6027
        }
      }
    },
    "2023-03-15": {
      "4": {
        "id": 4,
        "name": "Creed Bratton",
        ...
        "desktimeTime": 13105,
        ...
      }
    }
  },
  "__request_time": "1678957028"
}
```

- **Field Types:**
  - `notes`: object
  - `activeProject`: array/object

---

## Data Types and Field Conventions

- **integer:** Numeric ID
- **string:** Text field
- **boolean:** true/false
- **float:** Decimal number
- **object:** JSON object
- **array:** JSON array

---

## Best Practices

- Always check for API errors and handle them gracefully.
- Loop over each day in your sync window to fetch historical data (no bulk range fetch).
- Respect DeskTime API rate limits (if documented).
- Store and use DeskTime user IDs for mapping.
- Use HTTPS for all requests.

---

## Caveats

- DeskTime API does not support fetching attendance for a range of days in a single call. You must loop per day (or use `period=month` for a month's data).
- Data availability depends on the user's activity and DeskTime account configuration.
- API key should be kept secret and never exposed in client-side code.

---

## References

- [DeskTime API Docs](https://desktime.com/api)

---

## Example Sync Loop (PHP)

```php
for ($date = $start; $date <= $end; $date->addDay()) {
    $response = Http::get('https://api.desktime.com/v2/employees', [
        'date' => $date->format('Y-m-d'),
        'period' => 'day',
        'apiKey' => $apiKey,
    ]);
    // Process $response
}
```
