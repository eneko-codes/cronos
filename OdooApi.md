# Odoo API Integration Guide (Odoo v13, Cronos)

This document explains how the Odoo **v13** API is used in the Cronos application, focusing **only on the endpoints, models, and fields** that are actually consumed by our codebase. It is intended to help developers understand the data flows, field types, and integration points for maintenance and extension.

---

## 1. Authentication

All Odoo API calls require authentication using the `common.authenticate` method via JSON-RPC:

- **Endpoint:** `POST /jsonrpc`
- **Service:** `common`
- **Method:** `authenticate`
- **Params:** `[database, username, password, {}]`
- **Returns:** Odoo user ID (integer) if successful.

**Reference:** [Odoo 13 External API Authentication](https://www.odoo.com/documentation/13.0/developer/reference/external_api.html#authentication)

---

## 2. Data Access: search_read

All data is accessed using the `object.execute_kw` method with the `search_read` operation:

- **Endpoint:** `POST /jsonrpc`
- **Service:** `object`
- **Method:** `execute_kw`
- **Params:**
  - `db` (string): Database name
  - `uid` (int): Authenticated user ID
  - `password` (string): User password
  - `model` (string): Odoo model name (see below)
  - `method` (string): Always `search_read`
  - `args` (array): Search domain (e.g., `[["active", "=", true]]`)
  - `kwargs` (dict): `{fields: ["field1", "field2", ...]}`

**Reference:** [Odoo 13 search_read](https://www.odoo.com/documentation/13.0/developer/reference/external_api.html#search-and-read)

---

## 3. Odoo Models and Fields Used

Below are the Odoo models and fields accessed by this app, with their types and meaning as per Odoo 13 documentation.

### 3.1. `hr.employee` (Employee)

- **Fields used:**
  - `id` (integer)
  - `work_email` (string)
  - `name` (string)
  - `tz` (string)
  - `active` (boolean)
  - `department_id` (many2one → hr.department, returns `[id, name]`)
  - `category_ids` (many2many → hr.employee.category, returns list of `[id, name]`)
  - `resource_calendar_id` (many2one → resource.calendar, returns `[id, name]`)
  - `job_title` (string)
  - `parent_id` (many2one → hr.employee, returns `[id, name]`)

**Example Response:**

```json
{
  "id": 1,
  "work_email": "john.doe@company.com",
  "name": "John Doe",
  "tz": "Europe/Madrid",
  "active": true,
  "department_id": [2, "Engineering"],
  "category_ids": [
    [1, "Full Time"],
    [2, "Remote"]
  ],
  "resource_calendar_id": [1, "Standard 40h"],
  "job_title": "Developer",
  "parent_id": [3, "Jane Manager"]
}
```

- **Field Types:**
  - `department_id`, `resource_calendar_id`, `parent_id`: `[id, name]` array
  - `category_ids`: array of `[id, name]` arrays

### 3.2. `hr.department` (Department)

- **Fields used:**
  - `id` (integer)
  - `name` (string)
  - `active` (boolean)
  - `manager_id` (many2one → hr.employee, returns `[id, name]`)
  - `parent_id` (many2one → hr.department, returns `[id, name]`)

**Example Response:**

```json
{
  "id": 2,
  "name": "Engineering",
  "active": true,
  "manager_id": [3, "Jane Manager"],
  "parent_id": [1, "Company"]
}
```

- **Field Types:**
  - `manager_id`, `parent_id`: `[id, name]` array

### 3.3. `hr.employee.category` (Employee Category)

- **Fields used:**
  - `id` (integer)
  - `name` (string)
  - `active` (boolean)

**Example Response:**

```json
{
  "id": 1,
  "name": "Full Time",
  "active": true
}
```

### 3.4. `hr.leave.type` (Leave Type)

- **Fields used:**
  - `id` (integer)
  - `name` (string)
  - `active` (boolean)
  - `allocation_type` (selection: 'fixed', 'proportional', ...)
  - `validation_type` (selection: 'manager', 'both', ...)

**Example Response:**

```json
{
  "id": 1,
  "name": "Paid Time Off",
  "active": true,
  "allocation_type": "fixed",
  "validation_type": "manager"
}
```

### 3.5. `hr.leave` (Leave)

- **Fields used:**
  - `id` (integer)
  - `holiday_type` (selection: 'employee', 'category', ...)
  - `date_from` (datetime string, e.g. '2024-01-01 09:00:00')
  - `date_to` (datetime string)
  - `employee_id` (many2one → hr.employee, returns `[id, name]`)
  - `holiday_status_id` (many2one → hr.leave.type, returns `[id, name]`)
  - `state` (selection: 'draft', 'confirm', 'validate', ...)
  - `number_of_days` (float)
  - `category_id` (many2one → hr.employee.category, returns `[id, name]`)
  - `department_id` (many2one → hr.department, returns `[id, name]`)
  - `request_hour_from` (float)
  - `request_hour_to` (float)

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
  "category_id": [1, "Full Time"],
  "department_id": [2, "Engineering"],
  "request_hour_from": 9.0,
  "request_hour_to": 18.0
}
```

- **Field Types:**
  - `employee_id`, `holiday_status_id`, `category_id`, `department_id`: `[id, name]` array

### 3.6. `resource.calendar` (Work Schedule)

- **Fields used:**
  - `id` (integer)
  - `name` (string)
  - `active` (boolean)
  - `attendance_ids` (one2many → resource.calendar.attendance, returns list of IDs)
  - `hours_per_day` (float)
  - `two_weeks_calendar` (boolean)
  - `two_weeks_explanation` (string)
  - `flexible_hours` (boolean)
  - `create_date` (datetime string)
  - `write_date` (datetime string)
  - `create_uid` (many2one → res.users, returns [id, name])
  - `write_uid` (many2one → res.users, returns [id, name])

**Example Response:**

```json
{
  "id": 1,
  "name": "Standard 40h",
  "active": true,
  "attendance_ids": [1, 2, 3],
  "hours_per_day": 8.0,
  "two_weeks_calendar": false,
  "two_weeks_explanation": null,
  "flexible_hours": false,
  "create_date": "2024-01-01 09:00:00",
  "write_date": "2024-01-10 09:00:00",
  "create_uid": [1, "Admin"],
  "write_uid": [1, "Admin"]
}
```

### 3.7. `resource.calendar.attendance` (Schedule Detail)

- **Fields used:**
  - `id` (integer)
  - `calendar_id` (many2one → resource.calendar, returns [id, name])
  - `name` (string)
  - `dayofweek` (selection: '0'-'6')
  - `hour_from` (float)
  - `hour_to` (float)
  - `day_period` (string)
  - `week_type` (integer)
  - `date_from` (date)
  - `date_to` (date)
  - `display_type` (string)
  - `resource_id` (many2one → resource.resource, returns [id, name])
  - `active` (boolean)
  - `create_date` (datetime string)
  - `write_date` (datetime string)
  - `create_uid` (many2one → res.users, returns [id, name])
  - `write_uid` (many2one → res.users, returns [id, name])

> **About `week_type`:**
>
> - The `week_type` field is used for bi-weekly (two-week) work schedules.
> - **Values:**
>   - `0`: Applies to both weeks (default; slot is present every week)
>   - `1`: Applies to week 1 (odd weeks only)
>   - `2`: Applies to week 2 (even weeks only)
>
> **Examples:**
>
> - Standard weekly slot (every Monday):
>   ```json
>   { "dayofweek": "0", "hour_from": 9.0, "hour_to": 13.0, "week_type": 0 }
>   ```
> - Bi-weekly slot, week 1 only:
>   ```json
>   { "dayofweek": "0", "hour_from": 9.0, "hour_to": 13.0, "week_type": 1 }
>   ```
> - Bi-weekly slot, week 2 only:
>   ```json
>   { "dayofweek": "4", "hour_from": 14.0, "hour_to": 18.0, "week_type": 2 }
>   ```
>
> If you do not use bi-weekly schedules, this field will always be `0`.

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
  "week_type": 0,
  "date_from": "2024-01-01",
  "date_to": "2024-06-30",
  "display_type": null,
  "resource_id": null,
  "active": true,
  "create_date": "2024-01-01 09:00:00",
  "write_date": "2024-01-10 09:00:00",
  "create_uid": [1, "Admin"],
  "write_uid": [1, "Admin"]
}
```

- **Field Types:**
  - `calendar_id`: `[id, name]` array

---

## 4. Data Types and Field Conventions

- **integer:** Numeric ID
- **string:** Text field
- **boolean:** true/false (Note: If `null` is received from Odoo API for boolean fields like `active`, it is interpreted as `true`.)
- **float:** Decimal number
- **datetime:** String in 'YYYY-MM-DD HH:MM:SS' format
- **many2one:** Returns `[id, name]` array; use `id` for foreign key
- **many2many:** Returns list of `[id, name]` arrays; use list of `id`s
- **selection:** Returns string value of the selected option

**Reference:** [Odoo 13 ORM Field Types](https://www.odoo.com/documentation/13.0/reference/orm.html#fields)

---

## 5. Best Practices for Odoo API Integration

- Always use `search_read` with explicit `fields` for performance and clarity.
- Use correct data types when mapping to DTOs and Eloquent models.
- For `many2one` and `many2many`, extract the `id` (and `name` if needed for display).
- Handle timezones and datetime strings carefully.
- Validate all data before saving to your database.
- Be aware that boolean fields (like `active`) may be `null` in Odoo's API response, in which case they are treated as `true`.
- Reference the [Odoo 13 External API docs](https://www.odoo.com/documentation/13.0/developer/reference/external_api.html) for advanced usage.

---

## 6. Example JSON-RPC Request (search_read)

```json
{
  "jsonrpc": "2.0",
  "method": "call",
  "params": {
    "service": "object",
    "method": "execute_kw",
    "args": [
      "my_db", // database
      2, // uid
      "password", // password
      "hr.employee", // model
      "search_read", // method
      [[["active", "=", true]]], // domain
      { "fields": ["id", "name", "work_email", "department_id"] }
    ]
  },
  "id": 1
}
```

---

## 7. References

- [Odoo 13 External API Documentation](https://www.odoo.com/documentation/13.0/developer/reference/external_api.html)
- [Odoo 13 ORM Field Reference](https://www.odoo.com/documentation/13.0/reference/orm.html)
- [Odoo 13 hr.employee Model](https://www.odoo.com/documentation/13.0/reference/addons/hr/hr.employee.html)
- [Odoo 13 hr.department Model](https://www.odoo.com/documentation/13.0/reference/addons/hr/hr.department.html)
- [Odoo 13 hr.leave Model](https://www.odoo.com/documentation/13.0/reference/addons/hr/hr.leave.html)

---

This guide is specific to Odoo v13 and the Cronos app. For any changes in Odoo version or new fields/models, consult the official documentation and update this file accordingly.
