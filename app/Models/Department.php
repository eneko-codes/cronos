<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class Department
 *
 * Represents departments synchronized from Odoo.
 *
 * @property int $odoo_department_id
 * @property string $name
 * @property bool $active
 */
class Department extends Model
{
  /**
   * The table associated with the model.
   *
   * @var string
   */
  protected $table = 'departments';

  /**
   * The primary key for the model.
   *
   * @var string
   */
  protected $primaryKey = 'odoo_department_id';

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
  protected $fillable = ['odoo_department_id', 'name', 'active'];

  /**
   * The attributes that should be cast to native types.
   *
   * @var array
   */
  protected $casts = ['active' => 'boolean'];

  /**
   * The "booted" method of the model.
   *
   * Defines model event listeners.
   *
   * @return void
   */
  protected static function booted()
  {
    static::deleting(function ($department) {
      // Set 'department_id' to null for each associated user to emit model events
      foreach ($department->users as $user) {
        $user->department_id = null;
        $user->save();
      }
    });
  }

  /**
   * Get the users associated with the department.
   *
   * @return HasMany
   */
  public function users(): HasMany
  {
    return $this->hasMany(User::class, 'department_id', 'odoo_department_id');
  }
}
