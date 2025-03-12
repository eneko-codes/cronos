<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class LeaveType
 *
 * Represents leave types synchronized from Odoo.
 *
 * @property int $odoo_leave_type_id
 * @property string $name
 * @property bool $limit
 * @property bool $requires_allocation
 * @property bool $active
 */
class LeaveType extends Model
{
  /**
   * The table associated with the model.
   *
   * @var string
   */
  protected $table = 'leave_types';

  /**
   * The primary key for the model.
   *
   * @var string
   */
  protected $primaryKey = 'odoo_leave_type_id';

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
    'odoo_leave_type_id',
    'name',
    'limit',
    'requires_allocation',
    'active',
  ];

  /**
   * The attributes that should be cast to native types.
   *
   * @var array
   */
  protected $casts = [
    'limit' => 'boolean',
    'requires_allocation' => 'boolean',
    'active' => 'boolean',
  ];

  /**
   * Get the leave records associated with the leave type.
   *
   * @return HasMany
   */
  public function leaves(): HasMany
  {
    return $this->hasMany(
      UserLeave::class,
      'leave_type_id',
      'odoo_leave_type_id'
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
    static::deleting(function ($leaveType) {
      // Delete related UserLeave records to emit model events
      foreach ($leaveType->leaves as $leave) {
        $leave->delete();
      }
    });
  }
}
