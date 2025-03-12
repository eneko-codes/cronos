<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class UserAttendance
 *
 * Represents an employee's attendance record.
 *
 * @property int $user_id
 * @property \Carbon\Carbon $date
 * @property int $presence_seconds
 * @property bool $is_remote
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
   * @var array
   */
  protected $fillable = ['user_id', 'date', 'presence_seconds', 'is_remote'];

  /**
   * The attributes that should be cast to native types.
   *
   * @var array
   */
  protected $casts = [
    'is_remote' => 'boolean',
    'presence_seconds' => 'integer',
    'date' => 'date',
  ];

  /**
   * Get the user that owns the attendance record.
   *
   * @return BelongsTo
   */
  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }

  /**
   * Set the date in UTC.
   *
   * @param string $value
   * @return void
   */
  public function setDateAttribute($value): void
  {
    $this->attributes['date'] =
      $value instanceof Carbon
        ? $value->setTimezone('UTC')->toDateString()
        : Carbon::parse($value)->setTimezone('UTC')->toDateString();
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
    static::deleting(function ($userAttendance) {
      // Additional logic if needed
    });

    static::created(function ($userAttendance) {
      // Additional logic if needed
    });
  }
}
