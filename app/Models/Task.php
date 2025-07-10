<?php

declare(strict_types=1);

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
 * @property int $proofhub_task_id
 * @property int $proofhub_project_id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Project $project
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Project> $projects
 * @property-read int|null $projects_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TimeEntry> $timeEntries
 * @property-read int|null $time_entries_count
 * @property-read \App\Models\TaskUser|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 *
 * @method static \Database\Factories\TaskFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereProofhubProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereProofhubTaskId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Task whereUpdatedAt($value)
 *
 * @mixin \Eloquent
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
     * @var list<string>
     */
    protected $fillable = [
        'proofhub_task_id',
        'proofhub_project_id',
        'name',
        'status',
        'due_date',
        'description',
        'tags',
        'priority',
        'proofhub_created_at',
        'proofhub_updated_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'proofhub_created_at' => 'datetime',
        'proofhub_updated_at' => 'datetime',
    ];

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
