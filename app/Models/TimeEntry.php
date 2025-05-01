<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TimeEntry Model
 *
 * Represents time spent by a user on a specific project or task, synchronized from ProofHub.
 * TimeEntries track actual work performed, as opposed to schedules which track expected work hours.
 * Each entry contains information about the duration, date, project, and optionally the specific task.
 *
 * @property int $proofhub_time_entry_id Primary key (imported from ProofHub)
 * @property int $user_id Foreign key to users table (who logged the time)
 * @property string $proofhub_project_id Foreign key to projects table (which project the time was spent on)
 * @property string|null $proofhub_task_id Foreign key to tasks table (which task the time was spent on, if any)
 * @property string $status Status of the time entry in ProofHub
 * @property string $description Description of the work performed
 * @property \Carbon\Carbon $date Date when the work was performed (stored in UTC timezone)
 * @property int $seconds Duration of work in seconds
 * @property \Carbon\Carbon $proofhub_created_at When the entry was created in ProofHub
 * @property \Carbon\Carbon|null $created_at When record was created locally
 * @property \Carbon\Carbon|null $updated_at When record was last updated locally
 * @property-read \App\Models\User $user The user who logged this time
 * @property-read \App\Models\Project $project The project this time entry belongs to
 * @property-read \App\Models\Task|null $task The task this time entry is associated with (if any)
 * @property int $duration_seconds
 *
 * @method static \Database\Factories\TimeEntryFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimeEntry newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimeEntry newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimeEntry query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimeEntry whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimeEntry whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimeEntry whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimeEntry whereDurationSeconds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimeEntry whereProofhubCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimeEntry whereProofhubProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimeEntry whereProofhubTaskId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimeEntry whereProofhubTimeEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimeEntry whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimeEntry whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimeEntry whereUserId($value)
 *
 * @mixin \Eloquent
 */
class TimeEntry extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'time_entries';

    /**
     * The primary key for the model.
     *
     * Using the ProofHub ID as the primary key to maintain direct mapping
     * with the external system and avoid duplicate time entries.
     *
     * @var string
     */
    protected $primaryKey = 'proofhub_time_entry_id';

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
    protected $fillable = [
        'proofhub_time_entry_id',
        'user_id',
        'proofhub_project_id',
        'proofhub_task_id',
        'status',
        'description',
        'date',
        'duration_seconds',
        'proofhub_created_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * Note that dates are stored in UTC timezone for consistency
     * across different user timezones and integrations.
     *
     * @var array
     */
    protected $casts = [
        'date' => 'date',
        'duration_seconds' => 'integer',
        'proofhub_created_at' => 'datetime',
    ];

    /**
     * Set and get the date attribute with proper UTC timezone handling.
     *
     * This accessor/mutator ensures all dates are properly normalized to UTC
     * when stored, and properly converted when retrieved. This is essential for
     * consistent date handling across different user timezones.
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value
              ? Carbon::parse($value)->setTimezone('UTC')
              : null,
            set: fn ($value) => $value instanceof Carbon
              ? $value->setTimezone('UTC')->toDateString()
              : ($value
                ? Carbon::parse($value)->setTimezone('UTC')->toDateString()
                : null)
        );
    }

    /**
     * Get the user that owns the time entry.
     *
     * This relation connects to the employee who performed the work
     * and logged this time entry.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Get the project that the time entry belongs to.
     *
     * Every time entry must be associated with a project, representing
     * the work category or client project the time was spent on.
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
     * Get the task that the time entry is associated with.
     *
     * A time entry can optionally be associated with a specific task within
     * a project, providing more detailed tracking of work activities.
     * This may be null if the time was logged at the project level only.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(
            Task::class,
            'proofhub_task_id',
            'proofhub_task_id'
        );
    }
}
