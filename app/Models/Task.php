<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class Task
 *
 * Represents a task synchronized from ProofHub.
 *
 * @property string $proofhub_task_id
 * @property string $proofhub_project_id
 * @property string $name
 */
class Task extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tasks';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'proofhub_task_id';

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
    protected $fillable = ['proofhub_task_id', 'proofhub_project_id', 'name'];

    /**
     * The "booted" method of the model.
     *
     * Defines model event listeners.
     *
     * @return void
     */
    protected static function booted()
    {
        static::deleting(function ($task) {
            // Detach each user individually to emit model events
            foreach ($task->users as $user) {
                $task->users()->detach($user->id);
            }

            // Delete associated time entries to emit model events
            foreach ($task->timeEntries as $timeEntry) {
                $timeEntry->delete();
            }
        });
    }

    /**
     * The users that belong to the task.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'task_user',
            'proofhub_task_id',
            'user_id'
        )
            ->using(TaskUser::class)
            ->withTimestamps();
    }

    /**
     * The projects that the task belongs to.
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withTimestamps();
    }

    /**
     * The project that the task belongs to.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(
            Project::class,
            'proofhub_project_id',
            'proofhub_project_id'
        );
    }

    /**
     * The time entries associated with the task.
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(
            TimeEntry::class,
            'proofhub_task_id',
            'proofhub_task_id'
        );
    }
}
