# Odoo API Integration Reference (Odoo v13)

This document describes the **Odoo v13 API endpoints** used by the Cronos application. It focuses only on the specific endpoints, fields, and behaviors that our application consumes.

## Authentication

All API calls use JSON-RPC with the `common.authenticate` method:

- **Endpoint:** `POST /jsonrpc`
- **Service:** `common`
- **Method:** `authenticate`
- **Params:** `[database, username, password, {}]`
- **Returns:** `int` - Odoo user ID if successful

## Data Retrieval Pattern

All data endpoints use the same pattern:

- **Endpoint:** `POST /jsonrpc`
- **Service:** `object`
- **Method:** `execute_kw`
- **Params:** `[db, uid, password, model, "search_read", [domain], {fields: [...]}]`

---

## API Endpoints

### 1. `hr.employee` - Employee Data

**Purpose:** Retrieve employee/user information including department, categories, and work schedule assignments.

**Fields Requested:**

```php
[
    'id',                    // int
    'work_email',           // string|false
    'name',                 // string
    'tz',                   // string|false
    'active',               // bool|null
    'department_id',        // [int, string]|false
    'category_ids',         // int[]
    'resource_calendar_id', // [int, string]|false
    'job_title',            // string|false
    'parent_id'             // [int, string]|false
]
```

**Return Types:**

- `id`: `int` - Always present
- `work_email`: `string|false` - Employee email or false if not set
- `name`: `string` - Always present, employee full name
- `tz`: `string|false` - Timezone (e.g., "Europe/Madrid") or false
- `active`: `bool|null` - Active status, defaults to true if null
- `department_id`: `[int, string]|false` - `[id, name]` array or false
- `category_ids`: `int[]` - Array of category IDs (not `[id, name]` arrays)
- `resource_calendar_id`: `[int, string]|false` - `[id, name]` array or false
- `job_title`: `string|false` - Job title or false
- `parent_id`: `[int, string]|false` - Manager `[id, name]` array or false

**Example Response:**

```json
{
  "id": 1,
  "work_email": "john.doe@company.com",
  "name": "John Doe",
  "tz": "Europe/Madrid",
  "active": true,
  "department_id": [2, "Engineering"],
  "category_ids": [1, 3],
  "resource_calendar_id": [1, "Standard 40h"],
  "job_title": "Developer",
  "parent_id": [5, "Jane Manager"]
}
```

---

### 2. `hr.department` - Department Data

**Purpose:** Retrieve organizational department structure and hierarchy.

**Fields Requested:**

```php
[
    'id',         // int
    'name',       // string
    'active',     // bool|null
    'manager_id', // [int, string]|false
    'parent_id'   // [int, string]|false
]
```

**Return Types:**

- `id`: `int` - Always present
- `name`: `string` - Always present, department name
- `active`: `bool|null` - Active status, defaults to true if null
- `manager_id`: `[int, string]|false` - Manager employee `[id, name]` or false
- `parent_id`: `[int, string]|false` - Parent department `[id, name]` or false

**Example Response:**

```json
{
  "id": 2,
  "name": "Engineering",
  "active": true,
  "manager_id": [5, "Jane Manager"],
  "parent_id": [1, "Company"]
}
```

---

### 3. `hr.employee.category` - Employee Categories

**Purpose:** Retrieve employee classification categories (e.g., "Full Time", "Part Time").

**Fields Requested:**

```php
[
    'id',     // int
    'name',   // string
    'active'  // bool|null
]
```

**Return Types:**

- `id`: `int` - Always present
- `name`: `string` - Always present, category name
- `active`: `bool|null` - Active status, defaults to true if null

**Example Response:**

```json
{
  "id": 1,
  "name": "Full Time",
  "active": true
}
```

---

### 4. `hr.leave.type` - Leave Types

**Purpose:** Retrieve available leave/time-off types and their configuration.

**Fields Requested:**

```php
[
    'id',           // int
    'name',         // string
    'request_unit', // string|false
    'active',       // bool|null
    'create_date',  // string|false
    'write_date'    // string|false
]
```

**Return Types:**

- `id`: `int` - Always present
- `name`: `string` - Always present, leave type name
- `request_unit`: `string|false` - "day", "half_day", "hour", or false
- `active`: `bool|null` - Active status, defaults to true if null
- `create_date`: `string|false` - ISO datetime string or false
- `write_date`: `string|false` - ISO datetime string or false

**Example Response:**

```json
{
  "id": 1,
  "name": "Paid Time Off",
  "request_unit": "day",
  "active": true,
  "create_date": "2024-01-01 09:00:00",
  "write_date": "2024-01-15 14:30:00"
}
```

---

### 5. `hr.leave` - Leave Records

**Purpose:** Retrieve employee leave/time-off requests and approvals.

**Domain Filters Applied:**

```php
[
    ['state', 'in', ['validate', 'validate1', 'refuse', 'cancel', 'draft', 'confirm']],
    ['holiday_type', 'in', ['employee', 'category', 'department']]
]
```

**Fields Requested:**

```php
[
    'id',                // int
    'holiday_type',      // string
    'date_from',         // string
    'date_to',           // string
    'employee_id',       // [int, string]|false
    'holiday_status_id', // [int, string]|false
    'state',             // string
    'number_of_days',    // float|false
    'category_id',       // [int, string]|false
    'department_id',     // [int, string]|false
    'request_hour_from', // float|false
    'request_hour_to'    // float|false
]
```

**Return Types:**

- `id`: `int` - Always present
- `holiday_type`: `string` - "employee", "category", or "department"
- `date_from`: `string` - UTC datetime string (YYYY-MM-DD HH:MM:SS)
- `date_to`: `string` - UTC datetime string
- `employee_id`: `[int, string]|false` - Employee `[id, name]` or false
- `holiday_status_id`: `[int, string]|false` - Leave type `[id, name]` or false
- `state`: `string` - "draft", "confirm", "validate", "validate1", "refuse", "cancel"
- `number_of_days`: `float|false` - Number of days or false
- `category_id`: `[int, string]|false` - Category `[id, name]` or false
- `department_id`: `[int, string]|false` - Department `[id, name]` or false
- `request_hour_from`: `float|false` - Start hour (24h format) or false
- `request_hour_to`: `float|false` - End hour (24h format) or false

**Example Response:**

```json
{
  "id": 10,
  "holiday_type": "employee",
  "date_from": "2024-07-01 09:00:00",
  "date_to": "2024-07-05 18:00:00",
  "employee_id": [1, "John Doe"],
  "holiday_status_id": [1, "Paid Time Off"],
  "state": "validate",
  "number_of_days": 5.0,
  "category_id": false,
  "department_id": false,
  "request_hour_from": 9.0,
  "request_hour_to": 18.0
}
```

---

### 6. `resource.calendar` - Work Schedules

**Purpose:** Retrieve work schedule templates that define working hours patterns.

**Fields Requested:**

```php
[
    'id',                    // int
    'name',                  // string
    'active',                // bool|null
    'attendance_ids',        // int[]
    'hours_per_day',         // float|false
    'two_weeks_calendar',    // bool|false (optional)
    'two_weeks_explanation', // string|false (optional)
    'flexible_hours',        // bool|false (optional)
    'create_date',           // string|false
    'write_date'             // string|false
]
```

**Return Types:**

- `id`: `int` - Always present
- `name`: `string` - Always present, schedule name
- `active`: `bool|null` - Active status, defaults to true if null
- `attendance_ids`: `int[]` - Array of attendance detail IDs
- `hours_per_day`: `float|false` - Standard hours per day or false
- `two_weeks_calendar`: `bool|false` - Bi-weekly schedule flag (optional field)
- `two_weeks_explanation`: `string|false` - Bi-weekly description (optional field)
- `flexible_hours`: `bool|false` - Flexible hours flag (optional field)
- `create_date`: `string|false` - ISO datetime string or false
- `write_date`: `string|false` - ISO datetime string or false

**Example Response:**

```json
{
  "id": 1,
  "name": "Standard 40h",
  "active": true,
  "attendance_ids": [1, 2, 3, 4, 5],
  "hours_per_day": 8.0,
  "create_date": "2024-01-01 09:00:00",
  "write_date": "2024-01-10 09:00:00"
}
```

---

### 7. `resource.calendar.attendance` - Schedule Time Slots

**Purpose:** Retrieve individual time slots that make up work schedules (e.g., "Monday 9-13").

**Fields Requested:**

```php
[
    'id',           // int
    'calendar_id',  // [int, string]|false
    'name',         // string|false
    'dayofweek',    // string
    'hour_from',    // float|false
    'hour_to',      // float|false
    'day_period',   // string|false
    'week_type',    // int|false (optional)
    'date_from',    // string|false
    'date_to',      // string|false
    'active',       // bool|null
    'create_date',  // string|false
    'write_date'    // string|false
]
```

**Return Types:**

- `id`: `int` - Always present
- `calendar_id`: `[int, string]|false` - Schedule `[id, name]` or false
- `name`: `string|false` - Time slot name or false
- `dayofweek`: `string` - "0" (Monday) to "6" (Sunday)
- `hour_from`: `float|false` - Start hour (24h format, e.g., 9.0 = 9:00 AM)
- `hour_to`: `float|false` - End hour (24h format, e.g., 13.5 = 1:30 PM)
- `day_period`: `string|false` - "morning", "afternoon", etc. or false
- `week_type`: `int|false` - 0=both weeks, 1=week 1, 2=week 2 (optional field)
- `date_from`: `string|false` - Start date (YYYY-MM-DD) or false
- `date_to`: `string|false` - End date (YYYY-MM-DD) or false
- `active`: `bool|null` - Active status, defaults to true if null
- `create_date`: `string|false` - ISO datetime string or false
- `write_date`: `string|false` - ISO datetime string or false

**Example Response:**

```json
{
  "id": 1,
  "calendar_id": [1, "Standard 40h"],
  "name": "Monday Morning",
  "dayofweek": "0",
  "hour_from": 9.0,
  "hour_to": 13.0,
  "day_period": "morning",
  "date_from": "2024-01-01",
  "date_to": "2024-12-31",
  "active": true,
  "create_date": "2024-01-01 09:00:00",
  "write_date": "2024-01-10 09:00:00"
}
```

---

## Odoo API Quirks & Important Notes

### 1. **False vs Null Values**

- Odoo returns `false` (not `null`) for empty fields
- Always check `!== false` instead of using null coalescing `??`
- Example: `$email = ($item['work_email'] !== false) ? $item['work_email'] : null;`

### 2. **Boolean Field Defaults**

- Boolean fields like `active` may be `null` in responses
- When `null`, they should be treated as `true` by default
- Example: `(bool) ($item['active'] ?? true)`

### 3. **Many2one Field Format**

- Many2one fields return `[id, name]` arrays when set
- Return `false` when not set (never `null`)
- Example: `"department_id": [2, "Engineering"]` or `"department_id": false`

### 4. **Many2many Field Format**

- Many2many fields return arrays of IDs only (not `[id, name]` arrays)
- Example: `"category_ids": [1, 3, 5]`

### 5. **Datetime Fields**

- All datetime fields are in UTC
- Format: `"YYYY-MM-DD HH:MM:SS"`
- Can be `false` if not set

### 6. **Optional Fields**

- Some fields (marked above) may not exist in responses unless specific features are enabled
- Always check `isset()` before accessing optional fields
- Example: `$twoWeeks = isset($item['two_weeks_calendar']) ? $item['two_weeks_calendar'] : false;`

### 7. **Hour Format**

- Hours are floats in 24-hour format
- Example: `9.5` = 9:30 AM, `13.75` = 1:45 PM
