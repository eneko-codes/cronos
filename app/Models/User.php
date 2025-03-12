<?php

namespace App\Models;

use App\Notifications\WelcomeEmail;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
  use Notifiable;

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
   * Get the user's data for a given UTC date range.
   * This data includes schedules, leaves, attendances, and time entries.
   *
   * @param Carbon $startDate The start date (UTC)
   * @param Carbon $endDate   The end date (UTC)
   *
   * @return array An associative array with keys: schedules, leaves, attendances, time_entries
   */
  public function getDataForDateRange(Carbon $startDate, Carbon $endDate): array
  {
    return [
      'schedules' => $this->userSchedules()
        ->with('schedule.scheduleDetails')
        ->where('effective_from', '<=', $endDate)
        ->where(function ($query) use ($startDate) {
          $query
            ->where('effective_until', '>=', $startDate)
            ->orWhereNull('effective_until');
        })
        ->get(),

      'leaves' => $this->getLeaves($startDate, $endDate),

      'attendances' => $this->userAttendances()
        ->whereBetween('date', [$startDate, $endDate])
        ->get(),

      'time_entries' => $this->timeEntries()
        ->with(['project', 'task'])
        ->whereBetween('date', [$startDate, $endDate])
        ->get(),
    ];
  }

  /**
   * Retrieves leaves active for the user or the user's department/categories within a date range.
   *
   * @param Carbon|null $start The start date (UTC) or null
   * @param Carbon|null $end   The end date (UTC) or null
   *
   * @return \Illuminate\Database\Eloquent\Collection
   */
  public function getLeaves(?Carbon $start = null, ?Carbon $end = null)
  {
    $query = UserLeave::where(function ($query) {
      $query
        ->where('type', 'employee')
        ->where('user_id', $this->id)
        ->orWhere(function ($q) {
          $q->where('type', 'department')->where(
            'department_id',
            $this->department_id
          );
        })
        ->orWhere(function ($q) {
          $q->where('type', 'category')->whereIn(
            'category_id',
            $this->categories()->pluck('categories.odoo_category_id')
          );
        });
    });

    if ($start && $end) {
      $query->activeBetween($start, $end);
    }

    return $query->get();
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
