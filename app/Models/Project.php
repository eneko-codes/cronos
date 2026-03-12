<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Platform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

/**
 * Class Project
 *
 * Represents a project synchronized from ProofHub.
 *
 * @property int $proofhub_project_id
 * @property string $title
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Task> $tasks
 * @property-read int|null $tasks_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TimeEntry> $timeEntries
 * @property-read int|null $time_entries_count
 * @property-read int|null $project_time_entries_count
 * @property-read \App\Models\ProjectUser|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereProofhubProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Project whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Project extends Model
{
    use HasFactory, Searchable;

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
     * @var list<string>
     */
    protected $fillable = [
        'proofhub_project_id',
        'title',
        'status',
        'description',
        'proofhub_created_at',
        'proofhub_updated_at',
        'proofhub_creator_id',
        'proofhub_manager_id',
    ];

    protected $casts = [
        'proofhub_project_id' => 'integer',
        'status' => 'array',
        'proofhub_created_at' => 'datetime',
        'proofhub_updated_at' => 'datetime',
        'proofhub_creator_id' => 'integer',
        'proofhub_manager_id' => 'integer',
    ];

    /**
     * The users that belong to the project.
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
     * Get the user who created the project.
     *
     * Looks up the user via their ProofHub external identity since proofhub_creator_id
     * stores the ProofHub user ID, not the local user ID.
     */
    public function creator(): ?User
    {
        if (! $this->proofhub_creator_id) {
            return null;
        }

        return User::findByExternalId(Platform::ProofHub, (string) $this->proofhub_creator_id);
    }

    /**
     * Get the user who manages the project.
     *
     * Looks up the user via their ProofHub external identity since proofhub_manager_id
     * stores the ProofHub user ID, not the local user ID.
     */
    public function manager(): ?User
    {
        if (! $this->proofhub_manager_id) {
            return null;
        }

        return User::findByExternalId(Platform::ProofHub, (string) $this->proofhub_manager_id);
    }

    /**
     * The tasks that belong to the project.
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
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(
            TimeEntry::class,
            'proofhub_project_id',
            'proofhub_project_id'
        );
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'proofhub_project_id' => $this->proofhub_project_id,
            'title' => $this->title,
        ];
    }
}
