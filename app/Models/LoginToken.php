<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class LoginToken
 *
 * Represents a user's login token.
 *
 * @property string $id
 * @property int $user_id
 * @property string $token
 * @property \Carbon\Carbon $expires_at
 * @property bool $remember
 */
class LoginToken extends Model
{
  /**
   * The table associated with the model.
   *
   * @var string
   */
  protected $table = 'login_tokens';

  /**
   * Indicates if the model should be timestamped.
   *
   * @var bool
   */
  public $timestamps = true;

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = ['user_id', 'token', 'expires_at', 'remember'];

  /**
   * The attributes that should be cast to native types.
   *
   * @var array
   */
  protected $casts = [
    'expires_at' => 'datetime',
    'remember' => 'boolean',
  ];

  /**
   * Get the user that owns the login token.
   *
   * @return BelongsTo
   */
  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
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
    static::deleting(function ($loginToken) {
      // Additional logic if needed
    });

    static::created(function ($loginToken) {
      // Additional logic if needed
    });
  }
}
