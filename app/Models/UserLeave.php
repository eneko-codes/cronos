<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
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
 * @method static \Database\Factories\UserLeaveFactory factory($count = null, $state = [])
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
        'start_date' => 'datetime:Y-m-d H:i:s',
        'end_date' => 'datetime:Y-m-d H:i:s',
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

    /**
     * Scope a query to only include active leaves within a date range.
     *
     * Active leaves are those with 'validate' status and overlapping with
     * the specified date range. This is useful for checking availability
     * or generating reports for a specific period.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  CarbonInterface  $start  Beginning of the date range to check
     * @param  CarbonInterface  $end  End of the date range to check
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActiveBetween(
        $query,
        CarbonInterface $start,
        CarbonInterface $end
    ) {
        return $query
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

        // Convert decimal hours to hours and minutes format
        $fromHour = floor($this->request_hour_from);
        $fromMin = round(($this->request_hour_from - $fromHour) * 60);
        $toHour = floor($this->request_hour_to);
        $toMin = round(($this->request_hour_to - $toHour) * 60);

        return sprintf(
            '%02d:%02d - %02d:%02d',
            $fromHour,
            $fromMin,
            $toHour,
            $toMin
        );
    }
}
