<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Class UserSchedule
 *
 * Represents an employee's schedule assignment history.
 */
class UserSchedule extends Model
{
  use HasFactory;

  protected $table = 'user_schedules';
  public $timestamps = true;

  protected $fillable = [
    'user_id',
    'odoo_schedule_id',
    'effective_from',
    'effective_until',
  ];

  protected $casts = [
    'effective_from' => 'datetime',
    'effective_until' => 'datetime',
  ];

  protected $appends = ['duration'];

  /**
   * The user that owns this schedule assignment.
   *
   * @return BelongsTo
   */
  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }

  /**
   * The schedule associated with this assignment.
   *
   * @return BelongsTo
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
   * Accessor for the duration attribute.
   *
   * @return string|null
   */
  public function getDurationAttribute(): ?string
  {
    if ($this->effective_from && $this->effective_until) {
      return $this->effective_from->diffForHumans($this->effective_until, true);
    }
    return null;
  }

  protected static function boot()
  {
    parent::boot();

    static::created(function ($userSchedule) {
      // Additional logic can be added here if needed.
    });

    static::deleting(function ($userSchedule) {
      // Additional logic can be added here if needed.
    });
  }
}
