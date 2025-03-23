<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Class Project
 *
 * Represents a project synchronized from ProofHub.
 *
 * @property string $proofhub_project_id
 * @property string $name
 */
class Project extends Model
{
  use HasFactory;

  /**
   * The table associated with the model.
   *
   * @var string
   */
  protected $table = 'projects';

  /**
   * The primary key for the model.
   *
   * @var string
   */
  protected $primaryKey = 'proofhub_project_id';

  /**
   * Indicates if the IDs are auto-incrementing.
   *
   * @var bool
   */
  public $incrementing = false;

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = ['proofhub_project_id', 'name'];

  /**
   * The "booted" method of the model.
   *
   * Defines model event listeners.
   *
   * @return void
   */
  protected static function booted()
  {
    static::deleting(function ($project) {
      // Detach each user individually to emit model events
      foreach ($project->users as $user) {
        $project->users()->detach($user->id);
      }

      // Delete associated tasks and their time entries to emit model events
      foreach ($project->tasks as $task) {
        foreach ($task->timeEntries as $timeEntry) {
          $timeEntry->delete();
        }
        $task->delete();
      }

      // Delete associated time entries not linked to tasks to emit model events
      foreach ($project->timeEntries as $timeEntry) {
        $timeEntry->delete();
      }
    });
  }

  /**
   * The users that belong to the project.
   *
   * @return BelongsToMany
   */
  public function users(): BelongsToMany
  {
    return $this->belongsToMany(
      User::class,
      'project_user',
      'proofhub_project_id',
      'user_id'
    )
      ->using(ProjectUser::class)
      ->withTimestamps();
  }

  /**
   * The tasks that belong to the project.
   *
   * @return HasMany
   */
  public function tasks(): HasMany
  {
    return $this->hasMany(
      Task::class,
      'proofhub_project_id',
      'proofhub_project_id'
    );
  }

  /**
   * The time entries associated with the project.
   *
   * @return HasMany
   */
  public function timeEntries(): HasMany
  {
    return $this->hasMany(
      TimeEntry::class,
      'proofhub_project_id',
      'proofhub_project_id'
    );
  }
}
