## 📋 Table of Contents

1. [Overview](#overview)
2. [Configuration](#configuration)
3. [Data Architecture](#data-architecture)
4. [Implementation by Layer](#implementation-by-layer)
5. [Model Accessors](#model-accessors)
6. [Conversion in Actions](#conversion-in-actions)
7. [Practical Examples](#practical-examples)
8. [Best Practices](#best-practices)
9. [Troubleshooting](#troubleshooting)

---

## Overview

Cronos handles two types of temporal data with different strategies:

### 🌍 Business Data (External APIs)

**Stored in UTC in the database**

- Attendances (DeskTime, SystemPin)
- Leaves (Odoo)
- Time Entries (ProofHub)
- Schedules (Odoo)

### 📝 Technical Timestamps (Laravel)

**Stored in local timezone (Europe/Madrid)**

- `created_at`
- `updated_at`

---

## Configuration

### `config/app.php`

```php
'timezone' => env('APP_TIMEZONE', 'Europe/Madrid'),
```

**Purpose:** Defines the timezone for UI and Laravel timestamps.

### `config/database.php`

```php
'pgsql' => [
    // ... other settings
    'timezone' => 'UTC',
],
```

**Purpose:** Indicates that PostgreSQL should interpret timestamps as UTC (though Laravel doesn't always respect this for `created_at`/`updated_at`).

### `config/services.php`

```php
'systempin' => [
    'base_url' => env('SYSTEMPIN_BASE_URL'),
    'api_key' => env('SYSTEMPIN_API_KEY'),
    'timezone' => env('SYSTEMPIN_TIMEZONE', 'Europe/Madrid'),
],
```

**Purpose:** Timezone of the physical SystemPin terminal (configurable).

---

## Data Architecture

### Data Flow

```
┌─────────────────────────────────────────────────────────┐
│              EXTERNAL APIs (Source)                      │
├─────────────────────────────────────────────────────────┤
│ • Odoo:      UTC ("2025-10-17 08:00:00")               │
│ • ProofHub:  UTC+offset ("2025-10-17T08:00:00+00:00")  │
│ • DeskTime:  Company TZ ("2025-10-17 08:00:00")        │
│ • SystemPin: Local TZ ("2025-10-17 08:00:00")          │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
           ┌─────────────────────┐
           │  Actions (Parsing)  │
           │  Carbon::parse()    │
           │  ->utc()           │
           └─────────┬───────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│         POSTGRESQL DATABASE (Storage)                   │
├─────────────────────────────────────────────────────────┤
│ API Data (timestampTz):                                 │
│ • clock_in:     2025-10-17 06:00:00+00 (UTC)           │
│ • start_date:   2025-10-17 06:00:00+00 (UTC)           │
│                                                          │
│ Laravel Timestamps (timestamp):                         │
│ • created_at:   2025-10-17 08:00:00 (Europe/Madrid)    │
│ • updated_at:   2025-10-17 08:00:00 (Europe/Madrid)    │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
           ┌─────────────────────┐
           │ Model Accessors     │
           │ ->timezone()        │
           └─────────┬───────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│           UI / LIVEWIRE (Presentation)                  │
├─────────────────────────────────────────────────────────┤
│ ALL dates displayed in: Europe/Madrid                   │
│ • API Data: UTC → Madrid (via accessors)               │
│ • Laravel Timestamps: Madrid (native)                   │
└─────────────────────────────────────────────────────────┘
```

---

## Implementation by Layer

### 1. Migrations

#### Business Data (timestampTz)

```php
// database/migrations/2024_12_10_114902_create_user_attendances_table.php

Schema::create('user_attendances', function (Blueprint $table) {
    // ... other fields

    // Business fields: timestampTz (with timezone)
    $table->timestampTz('clock_in')->nullable()
          ->comment('UTC timestamp for clock in');
    $table->timestampTz('clock_out')->nullable()
          ->comment('UTC timestamp for clock out');

    // Laravel timestamps: timestamps() (without timezone)
    $table->timestamps();
});
```

**PostgreSQL creates:**

- `clock_in`: `timestamp with time zone` (timestamptz)
- `created_at`: `timestamp without time zone` (timestamp)

#### Fields with timestampTz

| Model          | Field                           | Type        | Usage                     |
| -------------- | ------------------------------- | ----------- | ------------------------- |
| UserAttendance | clock_in, clock_out             | timestampTz | Clock in/out times        |
| UserLeave      | start_date, end_date            | timestampTz | Leave dates               |
| UserSchedule   | effective_from, effective_until | timestampTz | Schedule validity periods |
| Project        | proofhub_created_at/updated_at  | timestampTz | ProofHub timestamps       |
| Task           | proofhub_created_at/updated_at  | timestampTz | ProofHub timestamps       |
| TimeEntry      | proofhub_created_at/updated_at  | timestampTz | ProofHub timestamps       |
| Schedule       | odoo_created_at/updated_at      | timestampTz | Odoo timestamps           |
| ScheduleDetail | odoo_created_at/updated_at      | timestampTz | Odoo timestamps           |

---

### 2. Models - Accessors

Critical models implement **Eloquent Attribute Accessors** for automatic timezone conversion.

#### UserAttendance

```php
<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class UserAttendance extends Model
{
    protected $casts = [
        'is_remote' => 'boolean',
        'duration_seconds' => 'integer',
        'date' => 'date',
        // clock_in and clock_out are NOT here (we use custom accessors)
    ];

    /**
     * Accessor for clock_in.
     * Stored: UTC
     * Retrieved: Europe/Madrid (APP_TIMEZONE)
     */
    protected function clockIn(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value
                ? Carbon::parse($value, 'UTC')->setTimezone(config('app.timezone'))
                : null,
            set: fn ($value) => match (true) {
                $value === null => null,
                $value instanceof Carbon => $value->utc()->toDateTimeString(),
                default => Carbon::parse($value)->utc()->toDateTimeString(),
            },
        );
    }

    /**
     * Accessor for clock_out.
     * Stored: UTC
     * Retrieved: Europe/Madrid (APP_TIMEZONE)
     */
    protected function clockOut(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value
                ? Carbon::parse($value, 'UTC')->setTimezone(config('app.timezone'))
                : null,
            set: fn ($value) => match (true) {
                $value === null => null,
                $value instanceof Carbon => $value->utc()->toDateTimeString(),
                default => Carbon::parse($value)->utc()->toDateTimeString(),
            },
        );
    }
}
```

#### UserLeave

```php
<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class UserLeave extends Model
{
    protected $casts = [
        // start_date and end_date are NOT here (we use custom accessors)
        'duration_days' => 'float',
        'request_hour_from' => 'float',
        'request_hour_to' => 'float',
    ];

    /**
     * Accessor for start_date.
     * Stored: UTC
     * Retrieved: Europe/Madrid (APP_TIMEZONE)
     */
    protected function startDate(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value
                ? Carbon::parse($value)->timezone(config('app.timezone'))
                : null,
            set: fn ($value) => match (true) {
                $value === null => null,
                $value instanceof Carbon => $value->utc()->toDateTimeString(),
                default => Carbon::parse($value, 'UTC')->toDateTimeString(),
            },
        );
    }

    /**
     * Accessor for end_date.
     * Stored: UTC
     * Retrieved: Europe/Madrid (APP_TIMEZONE)
     */
    protected function endDate(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value
                ? Carbon::parse($value)->timezone(config('app.timezone'))
                : null,
            set: fn ($value) => match (true) {
                $value === null => null,
                $value instanceof Carbon => $value->utc()->toDateTimeString(),
                default => Carbon::parse($value, 'UTC')->toDateTimeString(),
            },
        );
    }
}
```

---

### 3. Actions - Explicit UTC Conversion

All Actions receiving data from APIs **explicitly convert to UTC** before saving.

#### ProcessDesktimeAttendanceAction

```php
<?php

namespace App\Actions\Desktime;

use App\Clients\DesktimeApiClient;
use App\DataTransferObjects\Desktime\DesktimeAttendanceDTO;
use App\Models\UserAttendance;
use Carbon\Carbon;

final class ProcessDesktimeAttendanceAction
{
    public function execute(DesktimeAttendanceDTO $attendanceDto, string $timezone): void
    {
        // DeskTime API returns full datetime strings in company timezone
        // Parse with correct timezone and explicitly convert to UTC
        $clockIn = is_string($attendanceDto->arrived)
            ? Carbon::parse($attendanceDto->arrived, $timezone)->utc()
            : null;

        $clockOut = is_string($attendanceDto->left)
            ? Carbon::parse($attendanceDto->left, $timezone)->utc()
            : null;

        UserAttendance::create([
            'user_id' => $user->id,
            'date' => $attendanceDto->date,
            'clock_in' => $clockIn,  // Already in UTC
            'clock_out' => $clockOut, // Already in UTC
            'duration_seconds' => $durationSeconds,
            'is_remote' => true,
        ]);
    }
}
```

**Key:** `->utc()` converts the Carbon instance to UTC **before** passing to the model.

#### ProcessSystemPinAttendanceAction

```php
<?php

namespace App\Actions\SystemPin;

use Carbon\Carbon;

final class ProcessSystemPinAttendanceAction
{
    private function parseTime(string $timeString, string $date): ?Carbon
    {
        // Parse as configured timezone (local office time) and explicitly convert to UTC
        $timezone = config('services.systempin.timezone', 'Europe/Madrid');
        return Carbon::parse($date.' '.$timeFormatted, $timezone)->utc();
    }
}
```

#### ProcessOdooLeavesAction

```php
<?php

namespace App\Actions\Odoo;

use Carbon\Carbon;

final class ProcessOdooLeavesAction
{
    public function execute(OdooLeaveDTO $leaveDto): void
    {
        // Odoo already returns UTC, but we explicitly parse as UTC
        UserLeave::updateOrCreate(
            ['odoo_leave_id' => $leaveDto->id],
            [
                'start_date' => Carbon::parse($leaveDto->date_from, 'UTC')->utc(),
                'end_date' => Carbon::parse($leaveDto->date_to, 'UTC')->utc(),
                // ... other fields
            ]
        );
    }
}
```

---

## Conversion by Platform

### Odoo

**API Format:** `"2025-10-17 08:00:00"` (UTC)

```php
// Parsing
Carbon::parse($odooDate, 'UTC')->utc()
```

**Characteristics:**

- ✅ Already returns UTC
- ✅ Simple ISO format
- ✅ No timezone suffix

---

### ProofHub

**API Format:** `"2025-10-17T08:00:00+00:00"` (ISO 8601 with UTC offset)

```php
// Parsing (Carbon auto-detects timezone)
Carbon::parse($proofhubDate)->utc()
```

**Characteristics:**

- ✅ Full ISO 8601
- ✅ Includes timezone offset
- ✅ Carbon parses automatically

---

### DeskTime

**API Format:** `"2025-10-17 08:00:00"` (in account timezone)

```php
// Get account timezone
$timezone = $desktimeClient->getAccountTimezone(); // "Europe/London", "Europe/Madrid", etc.

// Parse with correct timezone
Carbon::parse($desktimeTime, $timezone)->utc()
```

**Characteristics:**

- ⚠️ Returns time in account timezone
- ✅ API provides `timezone_identifier` in `/company`
- ✅ We dynamically retrieve the timezone

---

### SystemPin

**API Format:** `"HHMM"` or `"YYYYMMDDHHMMSS"` (no timezone)

```php
// Use configurable timezone
$timezone = config('services.systempin.timezone', 'Europe/Madrid');

// Parsing
Carbon::parse($date . ' ' . $time, $timezone)->utc()
```

**Characteristics:**

- ⚠️ Does NOT provide timezone
- ✅ Configurable via `SYSTEMPIN_TIMEZONE`
- ⚠️ We assume local time of physical terminal

---

## Practical Examples

### Example 1: Save DeskTime Attendance

```php
// 1. DeskTime API returns
$apiResponse = [
    'arrived' => '2025-10-17 08:14:32',  // In DeskTime timezone (Europe/Madrid)
];

// 2. Get DeskTime timezone
$timezone = $desktimeClient->getAccountTimezone(); // "Europe/Madrid"

// 3. Parse with correct timezone
$clockIn = Carbon::parse($apiResponse['arrived'], $timezone); // 08:14:32 +02:00

// 4. Explicitly convert to UTC
$clockIn = $clockIn->utc(); // 06:14:32 +00:00

// 5. Save to DB
UserAttendance::create([
    'clock_in' => $clockIn,  // Saved as: "2025-10-17 06:14:32+00"
]);

// 6. Retrieve from DB
$attendance = UserAttendance::find(1);
echo $attendance->clock_in->format('H:i P');
// Output: "08:14 +02:00" (automatically converted to Europe/Madrid by accessor)
```

---

### Example 2: Compare with Schedule

```php
// User has scheduled start time: 08:00 Madrid
$scheduledStart = Carbon::parse('2025-10-17 08:00:00', 'Europe/Madrid');

// Retrieve attendance (already converted to Madrid by accessor)
$attendance = UserAttendance::find(1);
$actualStart = $attendance->clock_in; // 08:14 Madrid (accessor converts it)

// Calculate difference
$lateMinutes = $scheduledStart->diffInMinutes($actualStart, false);
// Result: 14 minutes late
```

---

### Example 3: DST Problem (Daylight Saving Time)

**Without UTC (INCORRECT):**

```php
// Storing in Europe/Madrid
$summer = Carbon::parse('2025-07-15 09:00:00', 'Europe/Madrid');
// DB: "2025-07-15 09:00:00" (but it's UTC+2)

$winter = Carbon::parse('2025-12-15 09:00:00', 'Europe/Madrid');
// DB: "2025-12-15 09:00:00" (but it's UTC+1)

// Duration comparison between two days
$duration = $summer->diffInHours($winter);
// INCORRECT RESULT: Doesn't consider DST change
```

**With UTC (CORRECT):**

```php
// Storing in UTC
$summer = Carbon::parse('2025-07-15 09:00:00', 'Europe/Madrid')->utc();
// DB: "2025-07-15 07:00:00+00"

$winter = Carbon::parse('2025-12-15 09:00:00', 'Europe/Madrid')->utc();
// DB: "2025-12-15 08:00:00+00"

// Correct comparison
$duration = $summer->diffInHours($winter);
// CORRECT RESULT: Considers DST change
```

---

## Best Practices

### ✅ DO

1. **Always parse with explicit timezone:**

   ```php
   Carbon::parse($dateString, 'Europe/Madrid')->utc()
   ```

2. **Use `->utc()` before saving:**

   ```php
   $model->field = Carbon::parse($value, $timezone)->utc();
   ```

3. **Implement accessors for timestampTz fields:**

   ```php
   protected function myField(): Attribute
   {
       return Attribute::make(
           get: fn ($value) => Carbon::parse($value, 'UTC')
               ->setTimezone(config('app.timezone')),
       );
   }
   ```

4. **Use `config('app.timezone')` in accessors:**

   ```php
   ->setTimezone(config('app.timezone'))
   ```

5. **Document the timezone of each field:**

   ```php
   $table->timestampTz('clock_in')
         ->comment('UTC timestamp for clock in');
   ```

---

### ❌ DON'T

1. **Don't save without converting to UTC:**

   ```php
   // ❌ BAD
   $model->field = Carbon::parse($value, 'Europe/Madrid');

   // ✅ GOOD
   $model->field = Carbon::parse($value, 'Europe/Madrid')->utc();
   ```

2. **Don't assume Carbon converts automatically:**

   ```php
   // ❌ BAD (Carbon keeps original timezone)
   $carbon = Carbon::parse($value);

   // ✅ GOOD (Specify timezone)
   $carbon = Carbon::parse($value, 'Europe/Madrid')->utc();
   ```

3. **Don't use `datetime` cast with timestampTz:**

   ```php
   // ❌ BAD
   protected $casts = [
       'clock_in' => 'datetime',
   ];

   // ✅ GOOD (use custom accessor)
   protected function clockIn(): Attribute { ... }
   ```

4. **Don't mix timestamps() and timestampsTz():**

   ```php
   // ❌ BAD (inconsistent)
   $table->timestamps();     // created_at without Tz
   $table->timestampTz(...); // custom field with Tz

   // ✅ GOOD (consistent)
   $table->timestamps();     // For Laravel
   $table->timestampTz(...); // For API data
   ```

---

## Troubleshooting

### Problem: Dates show 2 hours more/less

**Symptom:**

```php
$attendance->clock_in; // Shows 10:14 but should be 08:14
```

**Cause:** Not converting to UTC before saving.

**Solution:**

```php
// Add ->utc() in the Action
$clockIn = Carbon::parse($value, $timezone)->utc();
```

---

### Problem: Carbon keeps original timezone

**Symptom:**

```php
$carbon = Carbon::parse('2025-10-17 08:00:00+00');
echo $carbon->timezone; // "+00:00" (UTC) instead of "Europe/Madrid"
```

**Cause:** Carbon respects the timezone of the parsed string.

**Solution:**

```php
// In accessor, parse as UTC then convert
Carbon::parse($value, 'UTC')->setTimezone(config('app.timezone'))
```

---

### Problem: DST (Daylight Saving Time) causes errors

**Symptom:** Incorrect duration calculations between summer and winter.

**Cause:** Storing in local timezone instead of UTC.

**Solution:** Always store in UTC. UTC has no DST.

---

### Problem: Timezone doesn't match between environments

**Symptom:** Works locally but not in production.

**Cause:** Different `APP_TIMEZONE` or `DB_TIMEZONE`.

**Solution:**

```bash
# .env (all environments)
APP_TIMEZONE=Europe/Madrid
DB_TIMEZONE=UTC
```

---

## Verification

### Integration Test

```php
use Tests\TestCase;
use App\Models\UserAttendance;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_stores_in_utc_and_displays_in_app_timezone(): void
    {
        // Create attendance with Madrid time
        $madridTime = Carbon::parse('2025-10-17 08:14:32', 'Europe/Madrid');

        $attendance = UserAttendance::create([
            'user_id' => 1,
            'date' => '2025-10-17',
            'clock_in' => $madridTime->utc(), // Convert to UTC before saving
            'is_remote' => true,
        ]);

        // Verify it was saved in UTC
        $this->assertEquals(
            '2025-10-17 06:14:32+00',
            $attendance->getRawOriginal('clock_in')
        );

        // Verify accessor converts to Madrid
        $this->assertEquals(
            '08:14',
            $attendance->fresh()->clock_in->format('H:i')
        );

        $this->assertEquals(
            'Europe/Madrid',
            $attendance->fresh()->clock_in->timezone->getName()
        );
    }
}
```

### Manual Verification Script

```php
php artisan tinker

use App\Models\UserAttendance;
use Carbon\Carbon;

// Create test
$att = UserAttendance::create([
    'user_id' => 1,
    'date' => Carbon::now(),
    'clock_in' => Carbon::parse('2025-10-17 08:00:00', 'Europe/Madrid')->utc(),
    'is_remote' => true,
]);

// Verify
echo "DB (UTC): " . $att->getRawOriginal('clock_in') . PHP_EOL;
echo "Accessor (Madrid): " . $att->clock_in->format('Y-m-d H:i:s P') . PHP_EOL;
echo "Timezone: " . $att->clock_in->timezone->getName() . PHP_EOL;

// Cleanup
$att->delete();
```

**Expected Result:**

```
DB (UTC): 2025-10-17 06:00:00+00
Accessor (Madrid): 2025-10-17 08:00:00 +02:00
Timezone: Europe/Madrid
```

---

## Summary

### ✅ Final Architecture

| Data Type                 | Storage       | Display       | Method         |
| ------------------------- | ------------- | ------------- | -------------- |
| **API Data**              | UTC (`+00`)   | Europe/Madrid | Accessors      |
| **created_at/updated_at** | Europe/Madrid | Europe/Madrid | Laravel native |

### 📊 Benefits

1. ✅ **Industry standard** (UTC for business data)
2. ✅ **No DST problems** (daylight saving time)
3. ✅ **International scalability** (easy to add other timezones)
4. ✅ **Consistency** (all calculations in UTC)
5. ✅ **Flexibility** (display in any timezone via accessors)

### 🎯 Key Points

- **API Data:** UTC in DB, Europe/Madrid in UI
- **Laravel Timestamps:** Europe/Madrid in DB and UI
- **Explicit conversion:** `->utc()` before saving
- **Accessors:** Automatic conversion when retrieving
- **Configuration:** `APP_TIMEZONE=Europe/Madrid`, `DB_TIMEZONE=UTC`

---

## References

- [Laravel Dates & Mutators](https://laravel.com/docs/eloquent-mutators#date-casting)
- [Carbon Documentation](https://carbon.nesbot.com/docs/)
- [PostgreSQL Timezone Types](https://www.postgresql.org/docs/current/datatype-datetime.html)
- [ISO 8601 Standard](https://en.wikipedia.org/wiki/ISO_8601)
