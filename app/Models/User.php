<?php

namespace App\Models;

use Carbon\Carbon;
use App\Models\Department;
use App\Notifications\WelcomeEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Class User
 *
 * Represents an employee in the system synchronized from Odoo.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $timezone
 * @property int $odoo_id
 * @property string|null $desktime_id
 * @property string|null $proofhub_id
 * @property string|null $systempin_id
 * @property int|null $department_id
 * @property bool $is_admin
 * @property bool $do_not_track
 * @property \Carbon\Carbon|null $email_verified_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property bool $is_online
 */
class User extends Authenticatable
{
  use Notifiable, HasFactory;

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
    'muted_notifications',
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
    'muted_notifications' => 'boolean',
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
   * The "booted" method of the model.
   *
   * Defines model event listeners.
   *
   * @return void
   */
  protected static function booted()
  {
    static::created(function (User $user) {
      // Only notify if an email is present, welcome_email is enabled, and notifications aren't muted
      if (
        $user->email &&
        NotificationSetting::isEnabled('welcome_email') &&
        $user->shouldReceiveNotifications()
      ) {
        $user->notify(new WelcomeEmail($user));
      }
    });

    static::deleting(function (User $user) {
      // Delete hasMany relations individually to emit model events
      foreach ($user->loginTokens as $loginToken) {
        $loginToken->delete();
      }

      foreach ($user->userSchedules as $schedule) {
        $schedule->delete();
      }

      foreach ($user->userLeaves as $leave) {
        $leave->delete();
      }

      foreach ($user->userAttendances as $attendance) {
        $attendance->delete();
      }

      foreach ($user->timeEntries as $timeEntry) {
        $timeEntry->delete();
      }

      // Detach belongsToMany relations individually to emit model events
      foreach ($user->projects as $project) {
        $user->projects()->detach($project->id);
      }

      foreach ($user->categories as $category) {
        $user->categories()->detach($category->id);
      }

      foreach ($user->tasks as $task) {
        $user->tasks()->detach($task->proofhub_task_id);
      }
    });

    static::updating(function (User $user) {
      if (!$user->isDirty('do_not_track')) {
        return;
      }

      if ($user->do_not_track) {
        // Delete hasMany relations individually to emit model events
        foreach ($user->userSchedules as $schedule) {
          $schedule->delete();
        }

        foreach ($user->userLeaves as $leave) {
          $leave->delete();
        }

        foreach ($user->userAttendances as $attendance) {
          $attendance->delete();
        }

        foreach ($user->timeEntries as $timeEntry) {
          $timeEntry->delete();
        }

        // Detach belongsToMany relations individually to emit model events
        foreach ($user->projects as $project) {
          $user->projects()->detach($project->id);
        }

        foreach ($user->categories as $category) {
          $user->categories()->detach($category->id);
        }

        foreach ($user->tasks as $task) {
          $user->tasks()->detach($task->id);
        }
      }
    });
  }

  /**
   * Check if notifications should be sent to this user.
   */
  public function shouldReceiveNotifications(): bool
  {
    return !$this->muted_notifications;
  }

  /**
   * Scope a query to only include users who are trackable (do_not_track is false).
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
   * @return \Illuminate\Database\Eloquent\Relations\HasMany
   */
  public function sessions()
  {
    return $this->hasMany(Session::class, 'user_id', 'id');
  }

  /**
   * Get the login tokens associated with the user.
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
   * @return \Illuminate\Database\Eloquent\Relations\HasMany
   */
  public function userSchedules()
  {
    return $this->hasMany(UserSchedule::class);
  }

  /**
   * Get the leave records associated with the user.
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
   * @return \Illuminate\Database\Eloquent\Relations\HasMany
   */
  public function userAttendances()
  {
    return $this->hasMany(UserAttendance::class);
  }

  /**
   * Get all time entries associated with the user.
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
   * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
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
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function department(): BelongsTo
  {
    return $this->belongsTo(Department::class);
  }

  /**
   * The categories that the user belongs to.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
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
   * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
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
   * @return bool
   */
  public function getIsOnlineAttribute(): bool
  {
    return $this->sessions()->exists();
  }

  /**
   * Returns data for the user within a specified date range.
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
   *
   * @return bool
   */
  public function isAdmin(): bool
  {
    return (bool) $this->is_admin;
  }
}
