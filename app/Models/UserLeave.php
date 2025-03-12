<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
