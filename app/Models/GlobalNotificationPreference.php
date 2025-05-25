<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlobalNotificationPreference extends Model
{
    protected $table = 'global_notification_preferences';

    protected $primaryKey = 'notification_type';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['notification_type', 'enabled'];

    protected $casts = [
        'enabled' => 'boolean',
    ];
}
