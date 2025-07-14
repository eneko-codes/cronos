<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class ScheduleDetail
 *
 * Represents a time slot within a work schedule synchronized from Odoo.
 *
 * @property int $id
 * @property int $odoo_schedule_id
 * @property int $weekday
 * @property string $day_period
 * @property string|null $name Name/label for this schedule detail
 * @property string $start
 * @property string $end
 * @property int $odoo_detail_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $odoo_created_at Creation date of the record in Odoo
 * @property \Carbon\Carbon|null $odoo_updated_at Last update date of the record in Odoo
 * @property-read \App\Models\Schedule $schedule
 * @property bool $has_duplicates Dynamically added in ScheduleDetailView to mark duplicate entries
 * @property bool|null $active Whether the schedule detail is active (from Odoo)
 * @property int $week_type Determines whether the attendance applies to both weeks (0), week 1 (1), or week 2 (2)
 * @property string|null $date_from Optional start date for when the attendance is active
 * @property string|null $date_to Optional end date for when the attendance is active
 *
 * @method static \Database\Factories\ScheduleDetailFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleDetail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleDetail newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleDetail query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleDetail whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleDetail whereDayPeriod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleDetail whereEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleDetail whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleDetail whereOdooCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleDetail whereOdooDetailId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleDetail whereOdooScheduleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleDetail whereOdooUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleDetail whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleDetail whereStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleDetail whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleDetail whereWeekday($value)
 *
 * @mixin \Eloquent
 */
class ScheduleDetail extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'schedule_details';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'odoo_schedule_id',
        'odoo_detail_id',
        'weekday',
        'day_period',
        'week_type',
        'date_from',
        'date_to',
        'start',
        'end',
        'odoo_created_at',
        'odoo_updated_at',
        'name',
        'active',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'weekday' => 'integer',
        'week_type' => 'integer',
        'date_from' => 'date',
        'date_to' => 'date',
        'start' => 'datetime:H:i:s',
        'end' => 'datetime:H:i:s',
        'odoo_created_at' => 'datetime',
        'odoo_updated_at' => 'datetime',
        'active' => 'boolean',
    ];

    /**
     * Get the schedule that this detail belongs to.
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(
            Schedule::class,
            'odoo_schedule_id',
            'odoo_schedule_id'
        );
    }
}
