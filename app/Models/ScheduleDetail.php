<?php

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

    /**
     * The "booted" method of the model.
     *
     * Defines model event listeners.
     *
     * @return void
     */
    protected static function booted()
    {
        static::deleting(function ($scheduleDetail) {
            // Additional logic if needed
        });

        static::created(function ($scheduleDetail) {
            // Additional logic if needed
        });
    }
}
