<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
  protected $table = 'notification_settings';

  protected $fillable = ['key', 'enabled'];

  protected $casts = [
    'enabled' => 'boolean',
  ];

  /**
   * Quick helper to check if a notification (by key) is enabled.
   */
  public static function isEnabled(string $key): bool
  {
    return static::where('key', $key)->value('enabled') === true;
  }
}
