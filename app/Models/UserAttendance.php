<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
 * @property int $presence_seconds Total time present in seconds
 * @property bool $is_remote Whether this was remote work (true) or in-office work (false)
 * @property \Carbon\Carbon|null $start Start time of attendance (if available)
 * @property \Carbon\Carbon|null $end End time of attendance (if available)
 * @property \Carbon\Carbon|null $created_at When record was created locally
 * @property \Carbon\Carbon|null $updated_at When record was last updated locally
 * @property-read \App\Models\User $user The user this attendance record belongs to
 *
 * @method static \Database\Factories\UserAttendanceFactory factory($count = null, $state = [])
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
    use HasFactory;

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
        'presence_seconds',
        'is_remote',
        'start',
        'end',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * Date fields are stored in UTC format for consistency across
     * different time zones and data sources (Desktime and Systempin).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_remote' => 'boolean',
        'presence_seconds' => 'integer',
        'date' => 'date',
        'start' => 'datetime',
        'end' => 'datetime',
    ];

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
     * Set and get the date attribute with proper UTC timezone handling.
     *
     * Ensures date values are always normalized to UTC when stored
     * and properly formatted when retrieved. This maintains consistency
     * across different user timezones and data sources.
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Carbon::parse($value)->setTimezone('UTC'),
            set: fn ($value) => $value instanceof Carbon
              ? $value->setTimezone('UTC')->toDateString()
              : Carbon::parse($value)->setTimezone('UTC')->toDateString()
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
