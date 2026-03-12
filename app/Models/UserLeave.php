<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UserLeave Model
 *
 * Represents an employee's time off/leave record synchronized from Odoo.
 * Leave records can be associated with individual users, departments, or categories,
 * and have specific types (vacation, sick leave, etc.). They can also be full-day
 * or half-day (morning or afternoon).
 *
 * @property int $id Primary key
 * @property string $odoo_leave_id Unique identifier from Odoo
 * @property string $type Type of leave (employee, department, category)
 * @property \Carbon\Carbon $start_date Beginning of leave period
 * @property \Carbon\Carbon $end_date End of leave period
 * @property string $status Status of the leave (validate, refuse, etc.)
 * @property float $duration_days Length of leave in days (0.5 for half-day)
 * @property int|null $user_id Foreign key to users table (if type=employee)
 * @property int|null $department_id Foreign key to departments (if type=department)
 * @property int|null $category_id Foreign key to categories (if type=category)
 * @property int|null $leave_type_id Foreign key to leave_types table
 * @property float|null $request_hour_from Starting hour for partial day leaves
 * @property float|null $request_hour_to Ending hour for partial day leaves
 * @property \Carbon\Carbon|null $created_at When record was created
 * @property \Carbon\Carbon|null $updated_at When record was last updated
 * @property-read \App\Models\Category|null $category
 * @property-read \App\Models\Department|null $department
 * @property-read \App\Models\LeaveType|null $leaveType
 * @property-read \App\Models\User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserLeave activeBetween(\Carbon\CarbonInterface $start, \Carbon\CarbonInterface $end)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserLeave newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserLeave newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserLeave query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserLeave whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserLeave whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserLeave whereDepartmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserLeave whereDurationDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserLeave whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserLeave whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserLeave whereLeaveTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserLeave whereOdooLeaveId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserLeave whereRequestHourFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserLeave whereRequestHourTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserLeave whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserLeave whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserLeave whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserLeave whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserLeave whereUserId($value)
 *
 * @mixin \Eloquent
 */
class UserLeave extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_leaves';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'odoo_leave_id',
        'type',
        'start_date',
        'end_date',
        'status',
        'duration_days',
        'user_id',
        'department_id',
        'category_id',
        'leave_type_id',
        'request_hour_from',
        'request_hour_to',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // start_date and end_date handled by custom Attribute accessors below
        'duration_days' => 'float',
        'request_hour_from' => 'float',
        'request_hour_to' => 'float',
    ];

    /**
     * Get the user that owns the leave.
     *
     * Only applicable when this is an employee-specific leave (type=employee).
     * Will be null for department or category leaves.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the department associated with the leave.
     *
     * Only applicable when this is a department-wide leave (type=department).
     * Note: Uses odoo_department_id as the foreign key to match Odoo's identifiers.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(
            Department::class,
            'department_id',
            'odoo_department_id'
        );
    }

    /**
     * Get the category associated with the leave.
     *
     * Only applicable when this is a category-wide leave (type=category).
     * Note: Uses odoo_category_id as the foreign key to match Odoo's identifiers.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id', 'odoo_category_id');
    }

    /**
     * Get the leave type associated with the leave.
     *
     * This defines the nature of the leave (vacation, sick leave, unpaid leave, etc.)
     * Note: Uses odoo_leave_type_id as the foreign key to match Odoo's identifiers.
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(
            LeaveType::class,
            'leave_type_id',
            'odoo_leave_type_id'
        );
    }

    #[Scope]
    protected function activeBetween(Builder $query, CarbonInterface $start, CarbonInterface $end): void
    {
        $query
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start)
            ->where('status', 'validate');
    }

    /**
     * Determine if this is a half-day leave
     *
     * Half-day leaves in Odoo have a duration_days value of exactly 0.5.
     * This can be either morning or afternoon, determined by request_hour_from.
     *
     * @return bool True if this is a half-day leave, false otherwise
     */
    public function isHalfDay(): bool
    {
        // Odoo specifically uses duration_days = 0.5 for half-day leaves
        return $this->duration_days == 0.5;
    }

    /**
     * Determine if this is a half-day morning leave
     *
     * Morning leaves typically start at the beginning of the work day
     * and end around noon. In Odoo, these have request_hour_from < 12.0.
     *
     * @return bool True if this is a morning half-day leave, false otherwise
     */
    public function isMorningLeave(): bool
    {
        // Morning leaves typically start at the beginning of the work day
        // In Odoo, morning leaves typically have request_hour_from < 12.0
        if (! $this->isHalfDay() || $this->request_hour_from === null) {
            return false;
        }

        return $this->request_hour_from < 12.0;
    }

    /**
     * Determine if this is a half-day afternoon leave
     *
     * Afternoon leaves typically start around noon and end at the
     * end of the work day. In Odoo, these have request_hour_from >= 12.0.
     *
     * @return bool True if this is an afternoon half-day leave, false otherwise
     */
    public function isAfternoonLeave(): bool
    {
        // Afternoon leaves typically start after noon
        // In Odoo, afternoon leaves typically have request_hour_from >= 12.0
        if (! $this->isHalfDay() || $this->request_hour_from === null) {
            return false;
        }

        return $this->request_hour_from >= 12.0;
    }

    /**
     * Get formatted hours for half-day leave
     *
     * Converts the decimal hour values from Odoo (e.g., 9.5 for 9:30)
     * to a human-readable time range string (e.g., "09:30 - 13:30").
     *
     * @return string|null Formatted time range or null if not applicable
     */
    public function getFormattedHalfDayHours(): ?string
    {
        if (
            ! $this->isHalfDay() ||
            $this->request_hour_from === null ||
            $this->request_hour_to === null
        ) {
            return null;
        }

        // Convert decimal hours to hours and minutes format using Carbon
        $fromHour = (int) floor($this->request_hour_from);
        $fromMin = (int) round(($this->request_hour_from - $fromHour) * 60);
        $toHour = (int) floor($this->request_hour_to);
        $toMin = (int) round(($this->request_hour_to - $toHour) * 60);

        $fromTime = \Carbon\Carbon::createFromTime($fromHour, $fromMin)->format('H:i');
        $toTime = \Carbon\Carbon::createFromTime($toHour, $toMin)->format('H:i');

        return $fromTime.' - '.$toTime;
    }

    /**
     * Get leave duration as a Carbon interval instance.
     *
     * This accessor converts the duration_days field to a CarbonInterval
     * for easier manipulation and formatting in the application.
     */
    protected function duration(): Attribute
    {
        return Attribute::make(
            get: fn () => \Carbon\CarbonInterval::days((float) ($this->duration_days ?? 0))
        );
    }

    /**
     * Get formatted duration string for leave (e.g., "1 day", "0.5 day").
     *
     * This accessor provides a human-readable duration format
     * that can be used directly in views and reports.
     */
    protected function formattedDuration(): Attribute
    {
        return Attribute::make(
            get: fn () => ($this->duration_days ?? 0) > 0
                ? ($this->duration_days == 1 ? '1 day' : "{$this->duration_days} days")
                : ''
        );
    }

    /**
     * Format duration text based on duration days.
     *
     * Provides human-readable text for leave duration (e.g., "Half day", "1 day", "2.5 hours").
     * Used by components and services to display leave duration consistently.
     *
     * @param  float  $durationDays  The duration in days
     * @return string The formatted duration text
     */
    public static function formatDurationText(float $durationDays): string
    {
        if ($durationDays == 0.5) {
            return 'Half day';
        } elseif ($durationDays == 1) {
            return '1 day';
        } elseif ($durationDays < 1) {
            // For partial days, show as hours
            $hours = $durationDays * 8; // Convert to hours using 8-hour standard day

            return round($hours, 1).' hours';
        }

        return \Carbon\CarbonInterval::days((int) $durationDays)->cascade()->forHumans(['parts' => 2]);
    }

    /**
     * Get the start_date attribute, ensuring timezone is handled correctly.
     *
     * SETTER: When receiving API data (string without timezone), parse as UTC explicitly.
     *         When receiving Carbon instance, store as-is (already has timezone).
     *         Store to database in UTC format.
     *
     * GETTER: PostgreSQL returns "2024-07-01 09:00:00+00" (with timezone suffix).
     *         Parse this and convert to app timezone (Europe/Madrid) for display.
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
     * Get the end_date attribute, ensuring timezone is handled correctly.
     * Same logic as start_date - parse as UTC on set, display in Madrid on get.
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

    /**
     * Scope a query to only include leaves that overlap with a date range.
     *
     * A leave overlaps if its period intersects with the given date range.
     */
    #[Scope]
    protected function overlappingDates(Builder $query, CarbonInterface|string $from, CarbonInterface|string $to): void
    {
        $query->where('start_date', '<=', $to)
            ->where('end_date', '>=', $from);
    }

    /**
     * Scope a query to only include records for a specific user.
     */
    #[Scope]
    protected function forUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include leaves with a specific status.
     */
    #[Scope]
    protected function withStatus(Builder $query, string $status): void
    {
        $query->where('status', $status);
    }

    /**
     * Scope a query to only include approved/validated leaves.
     *
     * In Odoo, 'validate' is the status for approved leaves.
     */
    #[Scope]
    protected function approved(Builder $query): void
    {
        $query->where('status', 'validate');
    }

    /**
     * Scope a query to only include upcoming leaves (starting on or after today).
     */
    #[Scope]
    protected function upcoming(Builder $query): void
    {
        $query->where('start_date', '>=', now()->toDateString());
    }

    /**
     * Scope a query to only include leaves that are active on a specific date.
     *
     * A leave is active on a date if the date falls within its start and end dates.
     */
    #[Scope]
    protected function activeOn(Builder $query, CarbonInterface|string $date): void
    {
        $query->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date);
    }
}
