<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class Session
 *
 * Represents a user's session in the application.
 *
 * @property string $id
 * @property int $user_id
 * @property array $payload
 * @property int $last_activity
 */
class Session extends Model
{
  /**
   * The table associated with the model.
   *
   * @var string
   */
  protected $table = 'sessions';

  /**
   * The primary key for the model.
   *
   * @var string
   */
  protected $primaryKey = 'id';

  /**
   * Indicates if the IDs are auto-incrementing.
   *
   * @var bool
   */
  public $incrementing = false;

  /**
   * The "type" of the primary key ID.
   *
   * @var string
   */
  protected $keyType = 'string';

  /**
   * Indicates if the model should be timestamped.
   *
   * @var bool
   */
  public $timestamps = false;

  /**
   * The attributes that aren't mass assignable.
   *
   * @var array
   */
  protected $guarded = [];

  /**
   * The attributes that should be cast to native types.
   *
   * @var array
   */
  protected $casts = [
    'payload' => 'array',
    'last_activity' => 'integer',
  ];

  /**
   * Get the user that owns the session.
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
    static::deleting(function ($session) {
      // Additional logic if needed
    });

    static::created(function ($session) {
      // Additional logic if needed
    });
  }
}
