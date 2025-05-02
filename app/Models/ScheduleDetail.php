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
 * @property string $start
 * @property string $end
 * @property int $odoo_detail_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Schedule $schedule
 *
 * @method static \Database\Factories\ScheduleDetailFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleDetail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleDetail newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleDetail query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleDetail whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleDetail whereDayPeriod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleDetail whereEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleDetail whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleDetail whereOdooDetailId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleDetail whereOdooScheduleId($value)
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
     * @var array
     */
    protected $fillable = [
        'odoo_schedule_id',
        'odoo_detail_id',
        'weekday',
        'day_period',
        'start',
        'end',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'weekday' => 'integer',
        'start' => 'datetime:H:i:s',
        'end' => 'datetime:H:i:s',
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
