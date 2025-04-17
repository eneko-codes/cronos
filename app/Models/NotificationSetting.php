<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
  protected $table = 'notification_settings';

  protected $fillable = ['key', 'enabled', 'value'];

  protected $casts = [
    'enabled' => 'boolean',
  ];

  /**
   * Quick helper to check if a notification (by key) is enabled.
   */
  public static function isEnabled(string $key): bool
  {
    $setting = static::where('key', $key)->first();

    return $setting ? $setting->enabled === true : false;
  }

  /**
   * Quick helper to get the value of a setting (by key), with a default fallback.
   */
  public static function getValue(string $key, mixed $default = null): mixed
  {
    $setting = static::where('key', $key)->first();

    return $setting ? $setting->value : $default;
  }
}
