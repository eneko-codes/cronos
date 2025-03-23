<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Class UserAttendance
 *
 * Represents an employee's attendance record.
 *
 * @property int $user_id
 * @property \Carbon\Carbon $date
 * @property int $presence_seconds
 * @property bool $is_remote
 * @property \Carbon\Carbon|null $start
 * @property \Carbon\Carbon|null $end
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
   * @var array
   */
  protected $fillable = ['user_id', 'date', 'presence_seconds', 'is_remote', 'start', 'end'];

  /**
   * The attributes that should be cast to native types.
   *
   * @var array
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
   * @return BelongsTo
   */
  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }

  /**
   * Set and get the date attribute with proper UTC timezone handling.
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
