<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Schedule Model
 *
 * Represents a work schedule synchronized from Odoo's resource.calendar model.
 * A schedule defines when employees are expected to work (e.g., 9-5 weekdays).
 * It consists of multiple schedule details (time slots) specifying working hours
 * for each day of the week.
 *
 * @property int $odoo_schedule_id Primary key (from Odoo, not auto-incremented)
 * @property string $description Human-readable name of the schedule (e.g., "Standard 40 hours/week")
 * @property float $average_hours_day Average working hours per day
 * @property \Carbon\Carbon|null $created_at When record was created
 * @property \Carbon\Carbon|null $updated_at When record was last updated
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\ScheduleDetail[] $scheduleDetails Daily time slots in this schedule
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\UserSchedule[] $userSchedules User assignments to this schedule
 * @property-read int|null $schedule_details_count
 * @property-read int|null $user_schedules_count
 *
 * @method static \Database\Factories\ScheduleFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule whereAverageHoursDay($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule whereOdooScheduleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schedule whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Schedule extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'schedules';

    /**
     * The primary key for the model.
     *
     * Using odoo_schedule_id as the primary key to maintain direct mapping with Odoo.
     * This is not an auto-incrementing field, but defined by the external system.
     *
     * @var string
     */
    protected $primaryKey = 'odoo_schedule_id';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the primary key.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'odoo_schedule_id',
        'description',
        'average_hours_day',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'average_hours_day' => 'float',
    ];

    // /**
    //  * The relationships that should always be loaded.
    //  *
    //  * @var array
    //  */
    // protected $with = ['scheduleDetails', 'userSchedules']; // Commented out

    /**
     * Get the schedule details (time slots) associated with this schedule.
     *
     * Schedule details define the specific working hours for each day of the week.
     * For example, Monday 9:00-12:00 and 13:00-17:00, Tuesday 9:00-12:00, etc.
     * This relationship is the core of what defines a working schedule pattern.
     */
    public function scheduleDetails(): HasMany
    {
        return $this->hasMany(
            ScheduleDetail::class,
            'odoo_schedule_id',
            'odoo_schedule_id'
        );
    }

    /**
     * Get the user schedule assignments associated with this schedule.
     *
     * UserSchedule records track which employees are assigned to this schedule
     * and during which time periods. This enables tracking changes in employee
     * work schedules over time with effective dates.
     */
    public function userSchedules(): HasMany
    {
        return $this->hasMany(
            UserSchedule::class,
            'odoo_schedule_id',
            'odoo_schedule_id'
        );
    }
}
