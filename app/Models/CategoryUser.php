<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Class CategoryUser
 *
 * Represents the pivot model between Category and User.
 *
 * @property int $user_id
 * @property int $category_id
 */
class CategoryUser extends Pivot
{
  /**
   * The table associated with the pivot model.
   *
   * @var string
   */
  protected $table = 'category_user';

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
  protected $primaryKey = ['user_id', 'category_id'];

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
  protected $fillable = ['user_id', 'category_id'];

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
