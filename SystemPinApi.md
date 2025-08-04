# SystemPin API Documentation

SystemPin is a local attendance machine system that provides employee presence tracking through physical terminals. This document covers the API endpoints used by Cronos for synchronization.

---

## Authentication

- **Method:** Bearer token in Authorization header
- **Header:** `Authorization: Bearer {token}`

---

## Base URL

```
https://{server}:{port}/rest/presenciapin/
```

**Default:** `https://localhost:8371/rest/presenciapin/`

---

## Endpoints

### 1. Get Employees

**GET** `/GetDataFromDataBase?QueryID=13`

Retrieves employees with email addresses for user matching.

**Response:**

```json
{
  "data": [
    {
      "InternalEmployeeID": 878,
      "EmployeeID": "12345678X",
      "Name": "John Doe",
      "Email": "john.doe@company.com",
      "Active": true,
      "Department": "Engineering"
    }
  ]
}
```

**Field Types:**

- `InternalEmployeeID`: `int` - Internal system ID (used for matching)
- `EmployeeID`: `string` - External employee identifier (DNI/ID)
- `Name`: `string` - Employee full name
- `Email`: `string` - Email address for user matching
- `Active`: `boolean` - Employee active status
- `Department`: `string|null` - Department name

### 2. Get Attendance

**GET** `/GetDataFromPresenciaPin?QueryID=1&EmployeeFilter=*&DateFrom={YYYYMMDD}&DateTo={YYYYMMDD}`

Retrieves attendance data for specified date range.

**Parameters:**

- `DateFrom`: Start date (format: `20240101`)
- `DateTo`: End date (format: `20240131`)

**Response:**

```json
{
  "data": [
    {
      "InternalEmployeeID": 878,
      "EmployeeName": "John Doe",
      "Date": "2024-05-10",
      "ClockIn": "0830",
      "ClockOut": "1730",
      "WorkedMinutes": 480
    }
  ]
}
```

**Field Types:**

- `InternalEmployeeID`: `int` - Employee ID for matching with users
- `EmployeeName`: `string` - Employee name
- `Date`: `string` - Date in ISO format (YYYY-MM-DD)
- `ClockIn`: `string|null` - Clock-in time (HHMM or HH:MM format)
- `ClockOut`: `string|null` - Clock-out time (HHMM or HH:MM format)
- `WorkedMinutes`: `int|null` - Actual work time in minutes

---

## Configuration

### Environment Variables

```env
SYSTEMPIN_API_URL=https://localhost:8371
SYSTEMPIN_API_KEY=your_bearer_token_here
```

### Config File

Add to `config/services.php`:

```php
'systempin' => [
    'url' => env('SYSTEMPIN_API_URL'),
    'api_key' => env('SYSTEMPIN_API_KEY'),
],
```

---

## Integration Notes

- **User Matching:** By email address → updates `systempin_id` field
- **Attendance:** Always marked as `is_remote = false` (on-site)
- **Time Format:** Handles HHMM, HH:MM, and HHMMSS formats
- **Date Format:** Input requires YYYYMMDD, output is YYYY-MM-DD
- **Network:** Runs on local office network, may need VPN for remote access
