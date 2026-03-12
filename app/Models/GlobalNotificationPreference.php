<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Global notification preferences model.
 *
 * Stores system-wide notification settings:
 * - 'global_master' key: Master switch for all notifications
 * - NotificationType values: Per-type enable/disable settings
 *
 * @property string $notification_type The notification type (primary key)
 * @property bool $enabled Whether this notification type is enabled globally
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class GlobalNotificationPreference extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'global_notification_preferences';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'notification_type';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the primary key.
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'notification_type',
        'enabled',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'enabled' => 'boolean',
    ];
}
