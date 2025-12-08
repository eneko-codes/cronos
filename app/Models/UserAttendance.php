<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UserAttendance Model
 *
 * Represents an employee's attendance record synchronized from Desktime or Systempin.
 * This tracks when an employee was present at work, either remotely or in-office,
 * and for how long. Attendance records are the actual time spent at work, as opposed
 * to schedules which represent expected work hours.
 *
 * @property int $id Primary key
 * @property int $user_id Foreign key to users table
 * @property \Carbon\Carbon $date Date of the attendance record (stored in UTC)
 * @property int $duration_seconds Total duration in seconds
 * @property bool $is_remote Whether this was remote work (true) or in-office work (false)
 * @property \Carbon\Carbon|null $clock_in Start time of attendance (if available)
 * @property \Carbon\Carbon|null $clock_out End time of attendance (if available)
 * @property \Carbon\Carbon|null $created_at When record was created locally
 * @property \Carbon\Carbon|null $updated_at When record was last updated locally
 * @property-read \Carbon\CarbonInterval $duration The duration as a Carbon interval
 * @property-read string $formatted_duration The formatted duration string (e.g., "8h 30m")
 * @property-read bool $is_clocked_in Whether the user is currently clocked in
 * @property-read \App\Models\User $user The user this attendance record belongs to
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAttendance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAttendance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAttendance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAttendance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAttendance whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAttendance whereEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAttendance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAttendance whereIsRemote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAttendance wherePresenceSeconds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAttendance whereStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAttendance whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAttendance whereUserId($value)
 *
 * @mixin \Eloquent
 */
class UserAttendance extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_attendances';

    /**
     * The attributes that are mass assignable.
     *
     * All core attendance data can be mass-assigned during synchronization,
     * including user ID, date, duration, remote status, and timestamps.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
        'duration_seconds',
        'is_remote',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * Date fields are stored in UTC format for consistency across
     * different time zones and data sources (Desktime and Systempin).
     * clock_in and clock_out use custom Attribute accessors to ensure proper timezone handling.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_remote' => 'boolean',
        'duration_seconds' => 'integer',
        'date' => 'date',
    ];

    /**
     * Get/set the clock_in attribute with proper timezone conversion.
     * Stored in UTC, displayed in APP_TIMEZONE.
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
     * Get/set the clock_out attribute with proper timezone conversion.
     * Stored in UTC, displayed in APP_TIMEZONE.
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

    /**
     * Get the user that owns the attendance record.
     *
     * Links back to the employee whose attendance is being tracked.
     * This relationship is essential for reporting and analytics.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Set and get the date attribute with proper date handling.
     *
     * Ensures date values are properly parsed as Carbon instances when retrieved
     * and stored as date strings when saved. Date-only fields should not have
     * timezone conversion applied to maintain correct calendar dates.
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Carbon::parse($value),
            set: fn ($value) => $value instanceof Carbon
              ? $value->toDateString()
              : Carbon::parse($value)->toDateString()
        );
    }

    /**
     * Get duration as a Carbon interval instance.
     *
     * This accessor converts the duration_seconds field to a CarbonInterval
     * for easier manipulation and formatting in the application.
     */
    protected function duration(): Attribute
    {
        return Attribute::make(
            get: fn () => \Carbon\CarbonInterval::seconds((int) ($this->duration_seconds ?? 0))
        );
    }

    /**
     * Get formatted duration string (e.g., "8h 30m").
     *
     * This accessor provides a human-readable duration format
     * that can be used directly in views and reports.
     */
    protected function formattedDuration(): Attribute
    {
        return Attribute::make(
            get: fn () => \App\Services\DurationFormatterService::fromSeconds((int) ($this->duration_seconds ?? 0))
        );
    }

    /**
     * Check if the user is currently clocked in.
     *
     * Returns true if there's a clock_in time but no clock_out time.
     */
    protected function isClockedIn(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->clock_in && ! $this->clock_out
        );
    }

    public function scopeBetweenDates($query, $from, $to)
    {
        return $query->whereBetween('date', [$from, $to]);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    #[Scope]
    public function status($query, $status)
    {
        return $query->where('status', $status);
    }
}
