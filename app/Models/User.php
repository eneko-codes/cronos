<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
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
 * @property bool|null $is_online Virtual attribute that determines if the user is currently online (only works with database session driver)
 * @property string|null $remember_token
 * @property bool $is_active
 * @property string|null $job_title
 * @property int|null $odoo_manager_id
 * @property-read \App\Models\Schedule|null $activeSchedule
 * @property-read \App\Models\UserSchedule|null $activeUserSchedule
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Category> $categories
 * @property-read int|null $categories_count
 * @property-read \App\Models\Department|null $department
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LoginToken> $loginTokens
 * @property-read int|null $login_tokens_count
 * @property-read User|null $manager
 * @property-read \App\Models\UserNotificationPreference $notificationPreferences
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \App\Models\TaskUser|\App\Models\ProjectUser|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Project> $projects
 * @property-read int|null $projects_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Session> $sessions
 * @property-read int|null $sessions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $subordinates
 * @property-read int|null $subordinates_count
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
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static Builder<static>|User newModelQuery()
 * @method static Builder<static>|User newQuery()
 * @method static Builder<static>|User notTrackable()
 * @method static Builder<static>|User query()
 * @method static Builder<static>|User trackable()
 * @method static Builder<static>|User whereCreatedAt($value)
 * @method static Builder<static>|User whereDepartmentId($value)
 * @method static Builder<static>|User whereDesktimeId($value)
 * @method static Builder<static>|User whereDoNotTrack($value)
 * @method static Builder<static>|User whereEmail($value)
 * @method static Builder<static>|User whereId($value)
 * @method static Builder<static>|User whereIsActive($value)
 * @method static Builder<static>|User whereIsAdmin($value)
 * @method static Builder<static>|User whereJobTitle($value)
 * @method static Builder<static>|User whereName($value)
 * @method static Builder<static>|User whereOdooId($value)
 * @method static Builder<static>|User whereOdooManagerId($value)
 * @method static Builder<static>|User whereProofhubId($value)
 * @method static Builder<static>|User whereRememberToken($value)
 * @method static Builder<static>|User whereSystempinId($value)
 * @method static Builder<static>|User whereTimezone($value)
 * @method static Builder<static>|User whereUpdatedAt($value)
 *
 * @mixin \Eloquent
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
     * @var list<string>
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
        'job_title',
        'odoo_manager_id',
        'is_admin',
        'do_not_track',
        'muted_notifications',
        'remember_token',
        'is_active',
    ];

    /**
     * The attributes that are guarded.
     *
     * @var list<string>
     */
    protected $guarded = ['is_admin'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var list<string>
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
     * @var array<string, string>
     */
    protected $casts = [
        'is_admin' => 'boolean',
        'do_not_track' => 'boolean',
        'muted_notifications' => 'boolean',
        'is_active' => 'boolean',
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be appended to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = ['is_online'];

    /**
     * Scope a query to only include users who are trackable (do_not_track is false).
     *
     * Use this scope in sync operations to exclude users who have opted out of tracking.
     * Example: User::trackable()->where(...)->get();
     */
    public function scopeTrackable(Builder $query): Builder
    {
        return $query->where('do_not_track', false)->whereNotNull('odoo_id');
    }

    /**
     * Scope a query to only include users who are not trackable (do_not_track is true).
     *
     * Use this scope when you specifically need to find users who have opted out of tracking.
     * Example: User::notTrackable()->get();
     */
    public function scopeNotTrackable(Builder $query): Builder
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
            'category_id',
            'id',
            'odoo_category_id'
        );
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
        return (bool) $this->is_admin;
    }

    /**
     * Get the user's notification preferences.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<\App\Models\UserNotificationPreference, $this>
     */
    public function notificationPreferences(): HasOne
    {
        return $this->hasOne(UserNotificationPreference::class, 'user_id', 'id')
            ->withDefault();
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
     * Get the user's manager.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'odoo_manager_id', 'odoo_id');
    }

    /**
     * Get the user's direct subordinates.
     */
    public function subordinates(): HasMany
    {
        return $this->hasMany(User::class, 'odoo_manager_id', 'odoo_id');
    }
}
