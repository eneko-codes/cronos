<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property bool $mute_all
 * @property bool $schedule_change
 * @property bool $weekly_user_report
 * @property bool $leave_reminder
 * @property bool $api_down_warning
 * @property bool $admin_promotion_email
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNotificationPreference newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNotificationPreference newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNotificationPreference query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNotificationPreference whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNotificationPreference whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNotificationPreference whereLeaveReminder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNotificationPreference whereMuteAll($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNotificationPreference whereScheduleChange($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNotificationPreference whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNotificationPreference whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNotificationPreference whereWeeklyUserReport($value)
 *
 * @mixin \Eloquent
 */
class UserNotificationPreference extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'mute_all',
        'schedule_change',
        'weekly_user_report',
        'leave_reminder',
        'api_down_warning',
        'admin_promotion_email',
        // Add new preference keys here when needed
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'mute_all' => 'boolean',
        'schedule_change' => 'boolean',
        'weekly_user_report' => 'boolean',
        'leave_reminder' => 'boolean',
        'api_down_warning' => 'boolean',
        'admin_promotion_email' => 'boolean',
    ];

    /**
     * Get the user that owns the notification preferences.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
