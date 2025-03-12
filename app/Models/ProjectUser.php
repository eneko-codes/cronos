<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Class ProjectUser
 *
 * Represents the pivot model between Project and User.
 *
 * @property int $user_id
 * @property string $proofhub_project_id
 */
class ProjectUser extends Pivot
{
  /**
   * The table associated with the pivot model.
   *
   * @var string
   */
  protected $table = 'project_user';

  /**
   * Indicates if the primary key is auto-incrementing.
   *
   * @var bool
   */
  public $incrementing = false;

  /**
   * The primary key for the model.
   *
   * @var array
   */
  protected $primaryKey = ['user_id', 'proofhub_project_id'];

  /**
   * Indicates if the model should manage timestamps.
   *
   * @var bool
   */
  public $timestamps = true;

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = ['user_id', 'proofhub_project_id'];

  /**
   * The "booted" method of the model.
   *
   * Defines model event listeners.
   *
   * @return void
   */
  protected static function booted()
  {
    static::creating(function ($pivot) {
      // Additional logic if needed
    });

    static::deleting(function ($pivot) {
      // Additional logic if needed
    });
  }
}
