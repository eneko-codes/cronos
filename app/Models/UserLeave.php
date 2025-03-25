<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Class UserLeave
 *
 * Represents a leave record synchronized from Odoo.
 *
 * @property int $id
 * @property string $odoo_leave_id
 * @property string $type
 * @property \Carbon\Carbon $start_date
 * @property \Carbon\Carbon $end_date
 * @property string $status
 * @property float $duration_days
 * @property int|null $user_id
 * @property int|null $department_id
 * @property int|null $category_id
 * @property int|null $leave_type_id
 */
class UserLeave extends Model
{
  use HasFactory;

  /**
   * The table associated with the model.
   *
   * @var string
   */
  protected $table = 'user_leaves';

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = [
    'odoo_leave_id',
    'type',
    'start_date',
    'end_date',
    'status',
    'duration_days',
    'user_id',
    'department_id',
    'category_id',
    'leave_type_id',
    'request_hour_from',
    'request_hour_to',
  ];

  /**
   * The attributes that should be cast to native types.
   *
   * @var array
   */
  protected $casts = [
    'start_date' => 'datetime:Y-m-d H:i:s',
    'end_date' => 'datetime:Y-m-d H:i:s',
    'duration_days' => 'float',
    'request_hour_from' => 'float',
    'request_hour_to' => 'float',
  ];

  /**
   * Get the user that owns the leave.
   *
   * @return BelongsTo
   */
  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }

  /**
   * Get the department associated with the leave.
   *
   * @return BelongsTo
   */
  public function department(): BelongsTo
  {
    return $this->belongsTo(
      Department::class,
      'department_id',
      'odoo_department_id'
    );
  }

  /**
   * Get the category associated with the leave.
   *
   * @return BelongsTo
   */
  public function category(): BelongsTo
  {
    return $this->belongsTo(Category::class, 'category_id', 'odoo_category_id');
  }

  /**
   * Get the leave type associated with the leave.
   *
   * @return BelongsTo
   */
  public function leaveType(): BelongsTo
  {
    return $this->belongsTo(
      LeaveType::class,
      'leave_type_id',
      'odoo_leave_type_id'
    );
  }

  /**
   * Scope a query to only include active leaves within a date range.
   *
   * @param \Illuminate\Database\Eloquent\Builder $query
   * @param CarbonInterface $start
   * @param CarbonInterface $end
   * @return \Illuminate\Database\Eloquent\Builder
   */
  public function scopeActiveBetween(
    $query,
    CarbonInterface $start,
    CarbonInterface $end
  ) {
    return $query
      ->where('start_date', '<=', $end)
      ->where('end_date', '>=', $start)
      ->where('status', 'validate');
  }

  /**
   * Determine if this is a half-day leave
   * 
   * @return bool
   */
  public function isHalfDay(): bool
  {
    // Odoo specifically uses duration_days = 0.5 for half-day leaves
    return $this->duration_days == 0.5;
  }

  /**
   * Determine if this is a half-day morning leave
   * 
   * @return bool
   */
  public function isMorningLeave(): bool
  {
    // Morning leaves typically start at the beginning of the work day
    // In Odoo, morning leaves typically have request_hour_from < 12.0
    if (!$this->isHalfDay() || $this->request_hour_from === null) {
      return false;
    }
    
    return $this->request_hour_from < 12.0;
  }

  /**
   * Determine if this is a half-day afternoon leave
   * 
   * @return bool
   */
  public function isAfternoonLeave(): bool
  {
    // Afternoon leaves typically start after noon
    // In Odoo, afternoon leaves typically have request_hour_from >= 12.0
    if (!$this->isHalfDay() || $this->request_hour_from === null) {
      return false;
    }
    
    return $this->request_hour_from >= 12.0;
  }

  /**
   * Get formatted hours for half-day leave
   * 
   * @return string|null
   */
  public function getFormattedHalfDayHours(): ?string
  {
    if (!$this->isHalfDay() || $this->request_hour_from === null || $this->request_hour_to === null) {
      return null;
    }
    
    // Convert decimal hours to hours and minutes format
    $fromHour = floor($this->request_hour_from);
    $fromMin = round(($this->request_hour_from - $fromHour) * 60);
    $toHour = floor($this->request_hour_to);
    $toMin = round(($this->request_hour_to - $toHour) * 60);
    
    return sprintf(
      "%02d:%02d - %02d:%02d",
      $fromHour, $fromMin, $toHour, $toMin
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
    static::deleting(function ($userLeave) {
      // Additional logic if needed
    });

    static::created(function ($userLeave) {
      // Additional logic if needed
    });
  }
}
