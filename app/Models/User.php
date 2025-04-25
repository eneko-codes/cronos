<?php

namespace App\Models;

use App\Notifications\ApiDownWarning;
use App\Notifications\LeaveReminderNotification;
use App\Notifications\ScheduleChangeNotification;
use App\Notifications\WeeklyUserReportNotification;
use App\Notifications\WelcomeEmail;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;

/**
 * User Model
 *
 * Represents an application user (employee) that can be tracked across multiple systems.
 * Each user can have identifiers for various platforms (Odoo, Desktime, Proofhub, Systempin)
 * which are used for data synchronization with external services.
 *
 * Users with do_not_track=true will have their data automatically purged and will be
 * excluded from synchronization operations to maintain privacy.
 *
 * @property int $id Primary key
 * @property string $name Full name of the user
 * @property string $email Email address
 * @property string $timezone User's preferred timezone
 * @property int|null $odoo_id ID of the user in Odoo
 * @property int|null $desktime_id ID of the user in Desktime
 * @property int|null $proofhub_id ID of the user in Proofhub
 * @property int|null $systempin_id ID of the user in Systempin
 * @property int|null $department_id Foreign key to departments table
 * @property bool $is_admin Whether the user has admin privileges
 * @property bool $do_not_track Whether the user's data should be excluded from tracking operations
 * @property \Carbon\Carbon|null $email_verified_at When email was verified
 * @property \Carbon\Carbon|null $created_at When record was created
 * @property \Carbon\Carbon|null $updated_at When record was last updated
 * @property bool $is_online Virtual attribute that determines if the user is currently online
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

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
    public $incrementing = true;

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
    protected $fillable = [
        'name',
        'email',
        'timezone',
        'odoo_id',
        'desktime_id',
        'proofhub_id',
        'systempin_id',
        'department_id',
    ];

    /**
     * The attributes that are guarded.
     *
     * @var array
     */
    protected $guarded = ['is_admin'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'is_admin',
        'remember_token',
        'odoo_id',
        'desktime_id',
        'proofhub_id',
        'systempin_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_admin' => 'boolean',
        'do_not_track' => 'boolean',
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be appended to the model's array form.
     *
     * @var array
     */
    protected $appends = ['is_online'];

    /**
     * Check if notifications should be sent to this user.
     *
     * @return bool Returns false if notifications are muted, true otherwise
     */
    public function shouldReceiveNotifications(): bool
    {
        // First, check if notifications are globally disabled by an administrator.
        $isGloballyEnabled = (bool) Setting::getValue(
            'notifications.global_enabled',
            true
        ); // Default to true if setting doesn't exist
        if (! $isGloballyEnabled) {
            return false; // Globally disabled, user should not receive notifications.
        }

        // Next, check the user's specific preference to mute all their notifications.
        $preferences = $this->notificationPreferences;

        // If preferences record exists and mute_all is true, user has opted out.
        if ($preferences && $preferences->mute_all) {
            return false;
        }

        // If globally enabled and user hasn't muted all, they should receive notifications.
        return true;
    }

    /**
     * Scope a query to only include users who are trackable (do_not_track is false).
     *
     * Use this scope in sync operations to exclude users who have opted out of tracking.
     * Example: User::trackable()->where(...)->get();
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeTrackable($query)
    {
        return $query->where('do_not_track', false);
    }

    /**
     * Scope a query to only include users who are not trackable (do_not_track is true).
     *
     * Use this scope when you specifically need to find users who have opted out of tracking.
     * Example: User::notTrackable()->get();
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNotTrackable($query)
    {
        return $query->where('do_not_track', true);
    }

    /**
     * Get the sessions associated with the user.
     *
     * Used to determine if a user is currently online through the is_online attribute.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function sessions()
    {
        return $this->hasMany(Session::class, 'user_id', 'id');
    }

    /**
     * Get the login tokens associated with the user.
     *
     * These are used for authentication purposes.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function loginTokens()
    {
        return $this->hasMany(LoginToken::class);
    }

    /**
     * Get the schedule assignments associated with the user.
     *
     * Schedule assignments connect users to their work schedules imported from Odoo.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function userSchedules()
    {
        return $this->hasMany(UserSchedule::class);
    }

    /**
     * Get the leave records associated with the user.
     *
     * Leave records represent time off (vacation, sick leave, etc.) imported from Odoo.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function userLeaves()
    {
        return $this->hasMany(UserLeave::class);
    }

    /**
     * Get the attendance records associated with the user.
     *
     * Attendance records track when a user is present at work, imported from multiple sources.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function userAttendances()
    {
        return $this->hasMany(UserAttendance::class);
    }

    /**
     * Get all time entries associated with the user.
     *
     * Time entries track work time spent on specific tasks/projects, imported from Proofhub.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function timeEntries()
    {
        return $this->hasMany(TimeEntry::class);
    }

    /**
     * The projects that the user belongs to.
     *
     * Projects are imported from Proofhub and represent work assignments.
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(
            Project::class,
            'project_user',
            'user_id',
            'proofhub_project_id'
        )
            ->using(ProjectUser::class)
            ->withTimestamps();
    }

    /**
     * Get the department that the user belongs to.
     *
     * Departments are organizational units imported from Odoo.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * The categories that the user belongs to.
     *
     * Categories are groupings of employees imported from Odoo.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'category_user',
            'user_id',
            'category_id'
        )
            ->using(CategoryUser::class)
            ->withTimestamps();
    }

    /**
     * The tasks that the user is assigned to.
     *
     * Tasks represent work items imported from Proofhub.
     */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(
            Task::class,
            'task_user',
            'user_id',
            'proofhub_task_id'
        )
            ->using(TaskUser::class)
            ->withTimestamps();
    }

    /**
     * Get the online status of the user.
     *
     * A user is considered online if they have any active sessions.
     */
    public function getIsOnlineAttribute(): bool
    {
        return $this->sessions()->exists();
    }

    /**
     * Returns data for the user within a specified date range.
     *
     * Collects and organizes all user data (schedules, leaves, attendances, time entries)
     * within the provided date range for comprehensive reporting.
     *
     * @param  Carbon  $startDate  Beginning of the date range
     * @param  Carbon  $endDate  End of the date range
     * @return array Associative array of user data organized by date
     */
    public function getDataForDateRange(Carbon $startDate, Carbon $endDate): array
    {
        // Ensure we're working with dates at day precision
        $startDateObject = $startDate->copy()->startOfDay();
        $endDateObject = $endDate->copy()->endOfDay();

        // Get schedules with eager loading to avoid N+1 queries
        $schedules = $this->userSchedules()
            ->with(['schedule.scheduleDetails'])
            ->where('effective_from', '<=', $endDateObject)
            ->where(function ($query) use ($startDateObject) {
                $query
                    ->where('effective_until', '>=', $startDateObject)
                    ->orWhereNull('effective_until');
            })
            ->get();

        // Get leaves with eager loading
        $leaves = $this->userLeaves()
            ->with(['leaveType', 'department', 'category'])
            ->where(function ($query) use ($startDateObject, $endDateObject) {
                $query
                    ->whereBetween('start_date', [$startDateObject, $endDateObject])
                    ->orWhereBetween('end_date', [$startDateObject, $endDateObject])
                    ->orWhere(function ($innerQuery) use (
                        $startDateObject,
                        $endDateObject
                    ) {
                        $innerQuery
                            ->where('start_date', '<=', $startDateObject)
                            ->where('end_date', '>=', $endDateObject);
                    });
            })
            ->get();

        // Get attendances with eager loading
        $attendances = $this->userAttendances()
            ->whereBetween('date', [
                $startDateObject->toDateString(),
                $endDateObject->toDateString(),
            ])
            ->get();

        // Get time entries with eager loading
        $timeEntries = $this->timeEntries()
            ->with(['project', 'task'])
            ->whereBetween('date', [
                $startDateObject->toDateString(),
                $endDateObject->toDateString(),
            ])
            ->get();

        return [
            'schedules' => $schedules,
            'leaves' => $leaves,
            'attendances' => $attendances,
            'time_entries' => $timeEntries,
        ];
    }

    /**
     * Check if the user is an administrator
     */
    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    /**
     * Get the user's notification preferences.
     */
    public function notificationPreferences(): HasOne
    {
        return $this->hasOne(UserNotificationPreference::class)->withDefault();
    }

    /**
     * Centralized check to determine if a user should receive a specific notification.
     *
     * This method considers:
     * 1. Global notification enablement.
     * 2. User's master mute preference.
     * 3. System-wide toggle for the specific notification type (if applicable).
     * 4. User's individual preference for the specific notification type (if applicable).
     *
     * @param  Notification  $notification  The notification instance to be sent.
     * @return bool True if the user should receive the notification, false otherwise.
     */
    public function canReceiveNotification(Notification $notification): bool
    {
        // Step 1 & 2: Check global enable and user master mute.
        // We can reuse the existing method for this part.
        if (! $this->shouldReceiveNotifications()) {
            return false;
        }

        // Step 3: Check system-wide toggles for specific notification types.
        if ($notification instanceof WelcomeEmail) {
            if (
                ! (bool) Setting::getValue('notification.welcome_email.enabled', true)
            ) {
                return false; // Welcome emails are globally disabled.
            }
        }

        if ($notification instanceof ApiDownWarning) {
            if (
                ! (bool) Setting::getValue(
                    'notification.api_down_warning_mail.enabled',
                    true
                )
            ) {
                return false; // API down warnings are globally disabled.
            }
        }

        // Add checks for other system-wide toggles here (e.g., AdminPromotionEmail)
        // if ($notification instanceof AdminPromotionEmail) {
        //     if (!(bool)Setting::getValue('notification.admin_promotion.enabled', true)) {
        //         return false;
        //     }
        // }

        // Step 4: Check user's individual preferences for specific notification types.
        $preferences = $this->notificationPreferences; // Uses withDefault() ensuring it's an object

        // Add checks for specific user preference columns based on notification type.
        // These checks assume the corresponding Notification class exists.

        // Check for ScheduleChangeNotification preference:
        if ($notification instanceof ScheduleChangeNotification) {
            // Ensure the preference property exists and check its value
            if (
                property_exists($preferences, 'schedule_change') &&
                ! $preferences->schedule_change
            ) {
                return false; // User opted out of schedule change notifications.
            }
        }

        // Check for WeeklyUserReportNotification preference:
        if ($notification instanceof WeeklyUserReportNotification) {
            if (
                property_exists($preferences, 'weekly_user_report') &&
                ! $preferences->weekly_user_report
            ) {
                return false; // User opted out of weekly reports.
            }
        }

        // Check for LeaveReminderNotification preference:
        if ($notification instanceof LeaveReminderNotification) {
            if (
                property_exists($preferences, 'leave_reminder') &&
                ! $preferences->leave_reminder
            ) {
                return false; // User opted out of leave reminders.
            }
        }

        // If all checks pass, the user can receive the notification.
        return true;
    }
}
