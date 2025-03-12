<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Class Category
 *
 * Represents categories synchronized from Odoo.
 *
 * @property int $odoo_category_id
 * @property string $name
 * @property bool $active
 */
class Category extends Model
{
  /**
   * The table associated with the model.
   *
   * @var string
   */
  protected $table = 'categories';

  /**
   * The primary key for the model.
   *
   * @var string
   */
  protected $primaryKey = 'odoo_category_id';

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
  protected $fillable = ['odoo_category_id', 'name', 'active'];

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
    static::deleting(function ($category) {
      // Detach each user individually to emit model events
      foreach ($category->users as $user) {
        $category->users()->detach($user->id);
      }
    });
  }

  /**
   * The users that belong to the category.
   *
   * @return BelongsToMany
   */
  public function users(): BelongsToMany
  {
    return $this->belongsToMany(
      User::class,
      'category_user',
      'category_id',
      'user_id'
    )
      ->using(CategoryUser::class)
      ->withTimestamps();
  }
}
