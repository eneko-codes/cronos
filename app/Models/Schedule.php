<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class Schedule
 *
 * Represents a work schedule synchronized from Odoo.
 *
 * @property int $odoo_schedule_id
 * @property string $description
 * @property float $average_hours_day
 */
class Schedule extends Model
{
  /**
   * The table associated with the model.
   *
   * @var string
   */
  protected $table = 'schedules';

  /**
   * The primary key for the model.
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

  /**
   * The relationships that should always be loaded.
   *
   * @var array
   */
  protected $with = ['scheduleDetails', 'userSchedules'];

  /**
   * The "booted" method of the model.
   *
   * Defines model event listeners.
   *
   * @return void
   */
  protected static function booted()
  {
    static::deleting(function ($schedule) {
      // Delete associated schedule details to emit model events
      foreach ($schedule->scheduleDetails as $detail) {
        $detail->delete();
      }

      // Delete associated user schedule assignments to emit model events
      foreach ($schedule->userSchedules as $userSchedule) {
        $userSchedule->delete();
      }
    });
  }

  /**
   * Get the schedule details (time slots) associated with this schedule.
   *
   * @return HasMany
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
   * @return HasMany
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
