<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Platform;
use App\Enums\RoleType;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Scout\Searchable;

/**
 * User Model
 *
 * Represents an application user (employee) that can be tracked across multiple systems.
 * External platform identities (Odoo, DeskTime, ProofHub, SystemPin) are stored in the
 * user_external_identities table for cross-platform sync matching.
 *
 * Users with do_not_track=true will have their data automatically purged and will be
 * excluded from synchronization operations to maintain privacy.
 *
 * ## Email Architecture
 *
 * **users.email** (Primary Email):
 * - Purpose: Laravel authentication, email verification, ALL notifications
 * - Source: Synced from Odoo's `work_email` field (Odoo is the source of truth)
 * - Usage: Login, password reset, welcome emails, email verification, all notifications
 * - Verification: Via Laravel native MustVerifyEmail (email_verified_at column)
 *
 * **user_external_identities.external_email** (Platform-Specific Emails):
 * - Purpose: Store emails from each external platform for sync matching only
 * - Source: Synced from respective platform APIs
 * - Usage: Data synchronization, cross-platform user matching
 * - No verification needed - only used for sync purposes
 *
 * @property int $id Primary key
 * @property string $name Full name of the user
 * @property string $email Primary authentication and notification email
 * @property \Carbon\Carbon|null $email_verified_at When the email was verified
 * @property string|null $timezone User's preferred timezone
 * @property int|null $department_id Foreign key to departments table
 * @property RoleType $user_type Type of the user (e.g., Admin, User)
 * @property bool $do_not_track Whether the user's data should be excluded from tracking operations
 * @property bool $muted_notifications Whether to mute notifications for this user
 * @property bool $is_active Whether the user is active (synced from Odoo)
 * @property string|null $job_title User's job title
 * @property \Carbon\Carbon|null $created_at When record was created
 * @property \Carbon\Carbon|null $updated_at When record was last updated
 * @property bool|null $is_online Virtual attribute that determines if the user is currently online
 * @property string|null $remember_token
 * @property-read \App\Models\Schedule|null $activeSchedule
 * @property-read \App\Models\UserSchedule|null $activeUserSchedule
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Category> $categories
 * @property-read int|null $categories_count
 * @property-read \App\Models\Department|null $department
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserExternalIdentity> $externalIdentities
 * @property-read int|null $external_identities_count
 * @property-read \App\Models\UserNotificationPreference $notificationPreferences
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \App\Models\TaskUser|\App\Models\ProjectUser|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Project> $projects
 * @property-read int|null $projects_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Session> $sessions
 * @property-read int|null $sessions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Task> $tasks
 * @property-read int|null $tasks_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TimeEntry> $timeEntries
 * @property-read int|null $time_entries_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserAttendance> $userAttendances
 * @property-read int|null $user_attendances_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserLeave> $userLeaves
 * @property-read int|null $user_leaves_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserSchedule> $userSchedules
 * @property-read int|null $user_schedules_count
 *
 * @method static Builder<static>|User newModelQuery()
 * @method static Builder<static>|User newQuery()
 * @method static Builder<static>|User notTrackable()
 * @method static Builder<static>|User query()
 * @method static Builder<static>|User trackable()
 * @method static Builder<static>|User withoutAccount()
 * @method static Builder<static>|User whereCreatedAt($value)
 * @method static Builder<static>|User whereDepartmentId($value)
 * @method static Builder<static>|User whereDoNotTrack($value)
 * @method static Builder<static>|User whereEmail($value)
 * @method static Builder<static>|User whereId($value)
 * @method static Builder<static>|User whereIsActive($value)
 * @method static Builder<static>|User whereRoleType($value)
 * @method static Builder<static>|User whereJobTitle($value)
 * @method static Builder<static>|User whereName($value)
 * @method static Builder<static>|User whereRememberToken($value)
 * @method static Builder<static>|User whereTimezone($value)
 * @method static Builder<static>|User whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class User extends Authenticatable implements CanResetPassword, MustVerifyEmail
{
    use HasFactory, Notifiable, Searchable;

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
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'timezone',
        'department_id',
        'job_title',
        'user_type',
        'do_not_track',
        'muted_notifications',
        'remember_token',
        'is_active',
        'manually_archived_at',
    ];

    /**
     * The attributes that are guarded.
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'user_type' => RoleType::class,
        'do_not_track' => 'boolean',
        'muted_notifications' => 'boolean',
        'is_active' => 'boolean',
        'email_verified_at' => 'datetime',
        'manually_archived_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'timezone' => 'string',
    ];

    /**
     * The attributes that should be appended to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = ['is_online'];

    /**
     * Scope a query to only include users who are trackable (do_not_track is false and has Odoo identity).
     *
     * Use this scope in sync operations to exclude users who have opted out of tracking.
     * Example: User::trackable()->where(...)->get();
     */
    #[Scope]
    protected function trackable(Builder $query): void
    {
        $query->where('do_not_track', false)
            ->whereHas('externalIdentities', function (Builder $q): void {
                $q->where('platform', Platform::Odoo);
            });
    }

    /**
     * Scope a query to only include users who are not trackable (do_not_track is true).
     *
     * Use this scope when you specifically need to find users who have opted out of tracking.
     * Example: User::notTrackable()->get();
     */
    #[Scope]
    protected function notTrackable(Builder $query): void
    {
        $query->where('do_not_track', true);
    }

    /**
     * Scope a query to only include users without an account (no password set).
     *
     * Use this scope to find users who have been synced from external platforms
     * but have not yet set up their local account password.
     * Example: User::withoutAccount()->get();
     */
    #[Scope]
    protected function withoutAccount(Builder $query): void
    {
        $query->whereNull('password');
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
     * Get all external identities for this user.
     *
     * External identities link this user to their accounts on external platforms
     * (Odoo, DeskTime, ProofHub, SystemPin).
     */
    public function externalIdentities(): HasMany
    {
        return $this->hasMany(UserExternalIdentity::class);
    }

    /**
     * Get the external identity for a specific platform.
     */
    public function externalIdentityFor(Platform $platform): HasOne
    {
        return $this->hasOne(UserExternalIdentity::class)
            ->where('platform', $platform);
    }

    /**
     * Get the external ID for a specific platform.
     */
    public function getExternalIdFor(Platform $platform): ?string
    {
        return $this->externalIdentities
            ->firstWhere('platform', $platform)
            ?->external_id;
    }

    /**
     * Check if user has a linked identity for a specific platform.
     */
    public function hasExternalIdentity(Platform $platform): bool
    {
        return $this->externalIdentities()
            ->where('platform', $platform)
            ->exists();
    }

    /**
     * Find a user by their external platform ID.
     */
    public static function findByExternalId(Platform $platform, string $externalId): ?self
    {
        return self::whereHas('externalIdentities', function (Builder $query) use ($platform, $externalId): void {
            $query->where('platform', $platform)
                ->where('external_id', $externalId);
        })->first();
    }

    /**
     * Get the schedule assignments associated with the user.
     *
     * Schedule assignments connect users to their work schedules imported from Odoo.
     */
    public function userSchedules(): HasMany
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
     * Note: Department model uses odoo_department_id as its primary key.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'odoo_department_id');
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
            'category_id',
            'id',
            'odoo_category_id'
        )
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
     * A user is considered online if they have any active sessions *and*
     * the application session driver is set to 'database'. Otherwise, returns null.
     */
    public function getIsOnlineAttribute(): ?bool
    {
        // Only check sessions if the database driver is used
        if (config('session.driver') !== 'database') {
            return null;
        }

        return $this->sessions()->exists();
    }

    /**
     * Check if the user is an administrator
     */
    public function isAdmin(): bool
    {
        return $this->user_type === RoleType::Admin;
    }

    /**
     * Check if the user has the Maintenance role
     */
    public function isMaintenance(): bool
    {
        return $this->user_type === RoleType::Maintenance;
    }

    /**
     * Scope a query to only include active users.
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Scope a query to only include admin users.
     */
    #[Scope]
    protected function admin(Builder $query): void
    {
        $query->where('user_type', RoleType::Admin);
    }

    /**
     * Scope a query to only include maintenance users.
     */
    #[Scope]
    protected function maintenance(Builder $query): void
    {
        $query->where('user_type', RoleType::Maintenance);
    }

    /**
     * Scope a query to only include users with muted notifications.
     */
    #[Scope]
    protected function muted(Builder $query): void
    {
        $query->where('muted_notifications', true);
    }

    /**
     * Scope a query to only include inactive users.
     */
    #[Scope]
    protected function inactive(Builder $query): void
    {
        $query->where('is_active', false);
    }

    /**
     * Check if the user has set up their account (has a password).
     */
    public function hasAccount(): bool
    {
        return $this->password !== null;
    }

    /**
     * Get the user's notification preferences.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\UserNotificationPreference, $this>
     */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(UserNotificationPreference::class, 'user_id', 'id');
    }

    /**
     * Get the entity's notifications.
     * Override to use custom DatabaseNotification model with pruning support.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany<\App\Models\DatabaseNotification, $this>
     */
    public function notifications(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(DatabaseNotification::class, 'notifiable')->latest();
    }

    /**
     * Get the user's currently active schedule assignment.
     */
    public function activeUserSchedule(): HasOne
    {
        return $this->hasOne(UserSchedule::class)->whereNull('effective_until');
    }

    /**
     * Get the currently active schedule through the user schedule assignment.
     */
    public function activeSchedule(): HasOneThrough
    {
        return $this->hasOneThrough(
            Schedule::class,
            UserSchedule::class,
            'user_id', // Foreign key on UserSchedule table...
            'odoo_schedule_id', // Foreign key on Schedule table...
            'id', // Local key on User table...
            'odoo_schedule_id' // Local key on UserSchedule table...
        )->whereNull('user_schedules.effective_until');
    }

    /**
     * Get comprehensive user data (schedules, leaves, attendances, time entries)
     * for a specific user within a given date range.
     *
     * @param  \Carbon\Carbon  $startDate  Beginning of the date range.
     * @param  \Carbon\Carbon  $endDate  End of the date range.
     * @return array Associative array containing 'schedules', 'leaves', 'attendances', 'time_entries'.
     */
    public function getDataForDateRange(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate): array
    {
        // Ensure we're working with dates at day precision
        $startDateObject = $startDate->copy()->startOfDay();
        $endDateObject = $endDate->copy()->endOfDay();

        // Get schedules with eager loading to avoid N+1 queries
        $schedules = $this->userSchedules()
            ->with(['schedule.scheduleDetails'])
            ->where('effective_from', '<=', $endDateObject)
            ->where(function ($query) use ($startDateObject): void {
                $query
                    ->where('effective_until', '>=', $startDateObject)
                    ->orWhereNull('effective_until');
            })
            ->get();

        // Get leaves with eager loading
        $leaves = $this->userLeaves()
            ->with(['leaveType', 'department', 'category'])
            ->where(function ($query) use ($startDateObject, $endDateObject): void {
                $query
                    ->whereBetween('start_date', [$startDateObject, $endDateObject])
                    ->orWhereBetween('end_date', [$startDateObject, $endDateObject])
                    ->orWhere(function ($innerQuery) use (
                        $startDateObject,
                        $endDateObject
                    ): void {
                        $innerQuery
                            ->where('start_date', '<=', $startDateObject)
                            ->where('end_date', '>=', $endDateObject);
                    });
            })
            ->get();

        // Get attendances with eager loading
        $attendances = $this->userAttendances()
            ->betweenDates(
                $startDateObject->toDateString(),
                $endDateObject->toDateString()
            )
            ->get();

        // Get time entries with eager loading
        $timeEntries = $this->timeEntries()
            ->with(['project', 'task'])
            ->betweenDates(
                $startDateObject->toDateString(),
                $endDateObject->toDateString()
            )
            ->get();

        return [
            'schedules' => $schedules,
            'leaves' => $leaves,
            'attendances' => $attendances,
            'time_entries' => $timeEntries,
        ];
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }

    /**
     * Send the password reset notification.
     *
     * This method overrides Laravel's default password reset notification
     * to use our custom queued notification for asynchronous processing.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Send the email verification notification.
     *
     * This method overrides Laravel's default email verification notification
     * to use our custom queued notification for asynchronous processing.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }
}
