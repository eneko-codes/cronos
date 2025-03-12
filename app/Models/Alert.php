<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Notifications\DatabaseNotification;

class Alert extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'type',
        'user_id',
        'admin_only',
        'title',
        'message',
        'data',
        'resolved',
        'expires_at',
        'resolved_by',
        'resolved_at',
        'notification_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'admin_only' => 'boolean',
        'resolved' => 'boolean',
        'data' => 'array',
        'expires_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the user that the alert is for.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user that resolved the alert.
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Get the notification associated with this alert.
     */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(DatabaseNotification::class, 'notification_id');
    }

    /**
     * Scope to get only active alerts (not resolved and not expired).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('resolved', false)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', Carbon::now());
            });
    }

    /**
     * Scope to get alerts that are visible to a specific user
     * (includes admin-only alerts if the user is an admin).
     */
    public function scopeVisibleTo(Builder $query, User $user = null): Builder
    {
        if (!$user) {
            return $query->where('admin_only', false);
        }
        
        return $query->where(function ($query) use ($user) {
            // Get alerts specifically for this user
            $query->where('user_id', $user->id);
            
            // Or alerts for all users (null user_id and not admin only)
            $query->orWhere(function($q) {
                $q->whereNull('user_id')
                  ->where('admin_only', false);
            });
            
            // Or alerts for all admins if this user is an admin
            if ($user->isAdmin()) {
                $query->orWhere(function ($q) {
                    $q->whereNull('user_id')
                      ->where('admin_only', true);
                });
            }
        });
    }

    /**
     * Scope to get only admin alerts.
     */
    public function scopeAdminOnly(Builder $query): Builder
    {
        return $query->where('admin_only', true);
    }

    /**
     * Resolve this alert.
     */
    public function resolve(User $user = null): self
    {
        $this->update([
            'resolved' => true,
            'resolved_by' => optional($user)->id,
            'resolved_at' => Carbon::now(),
        ]);

        // Mark the associated notification as read if it exists
        if ($this->notification_id) {
            $notification = DatabaseNotification::find($this->notification_id);
            if ($notification && $notification->read_at === null) {
                $notification->markAsRead();
            }
        }

        return $this;
    }

    /**
     * Create an alert from a Laravel notification
     */
    public static function createFromNotification(DatabaseNotification $notification): self
    {
        $data = $notification->data;
        $type = $data['type'] ?? 'info';
        $userId = $notification->notifiable_id;
        $adminOnly = $data['admin_only'] ?? false;
        
        return self::create([
            'type' => $type,
            'user_id' => $userId,
            'admin_only' => $adminOnly,
            'title' => $data['title'] ?? 'New Notification',
            'message' => $data['message'] ?? '',
            'data' => $data,
            'notification_id' => $notification->id,
        ]);
    }

    /**
     * Sync all unread notifications to create corresponding alerts
     */
    public static function syncUnreadNotifications(): int
    {
        $count = 0;
        $notifications = DatabaseNotification::whereNull('read_at')->get();
        
        foreach ($notifications as $notification) {
            // Check if an alert already exists for this notification
            $exists = self::where('notification_id', $notification->id)->exists();
            if (!$exists) {
                self::createFromNotification($notification);
                $count++;
            }
        }
        
        return $count;
    }

    /**
     * Create a new duplicate schedule alert.
     * Checks for existing unresolved alerts for the same schedule_id before creating a new one.
     */
    public static function createScheduleDuplicateAlert(array $data): self
    {
        // Check if there's already an unresolved alert for this schedule
        $existingAlert = self::where('type', 'schedule_duplicates')
            ->where('resolved', false)
            ->whereJsonContains('data->schedule_id', $data['schedule_id'])
            ->first();

        if ($existingAlert) {
            // Update the existing alert's data with the latest duplicate information
            $existingAlert->update([
                'data' => $data,
                'updated_at' => now(),
            ]);
            return $existingAlert;
        }

        // Create a new alert if none exists
        return self::create([
            'type' => 'schedule_duplicates',
            'admin_only' => true,
            'title' => 'Duplicate Schedule Details Detected',
            'message' => "Schedule #{$data['schedule_id']} ({$data['schedule_name']}) has duplicate details.",
            'data' => $data,
        ]);
    }
}
