<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Class TaskUser
 *
 * Represents the pivot model between Task and User.
 *
 * @property int $id
 * @property int $user_id
 * @property int $proofhub_task_id
 */
class TaskUser extends Pivot
{
  /**
   * The table associated with the pivot model.
   *
   * @var string
   */
  protected $table = 'task_user';

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = ['user_id', 'proofhub_task_id'];

  /**
   * Indicates if the primary key is auto-incrementing.
   *
   * @var bool
   */
  public $incrementing = true;

  /**
   * The primary key for the model.
   *
   * @var string
   */
  protected $primaryKey = 'id';

  /**
   * Indicates if the model should manage timestamps.
   *
   * @var bool
   */
  public $timestamps = true;
}
