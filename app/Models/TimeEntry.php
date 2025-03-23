<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;

/**
 * Class TimeEntry
 *
 * Represents a time entry synchronized from ProofHub.
 *
 * @property int $proofhub_time_entry_id
 * @property int $user_id
 * @property string $proofhub_project_id
 * @property string|null $proofhub_task_id
 * @property string $status
 * @property string $description
 * @property \Carbon\Carbon $date
 * @property int $duration_seconds
 * @property \Carbon\Carbon $proofhub_created_at
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
   * @var array
   */
  protected $casts = [
    'date' => 'date',
    'duration_seconds' => 'integer',
    'logged_hours' => 'integer',
    'logged_mins' => 'integer',
    'end_date' => 'date',
    'start_date' => 'date',
    'proofhub_created_at' => 'datetime',
  ];

  /**
   * Set and get the date attribute with proper UTC timezone handling.
   */
  protected function date(): Attribute
  {
    return Attribute::make(
      get: fn ($value) => $value ? Carbon::parse($value)->setTimezone('UTC') : null,
      set: fn ($value) => $value instanceof Carbon 
        ? $value->setTimezone('UTC')->toDateString()
        : ($value ? Carbon::parse($value)->setTimezone('UTC')->toDateString() : null)
    );
  }

  /**
   * Get the user that owns the time entry.
   *
   * @return BelongsTo
   */
  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class, 'user_id', 'id');
  }

  /**
   * Get the project that the time entry belongs to.
   *
   * @return BelongsTo
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
   * @return BelongsTo
   */
  public function task(): BelongsTo
  {
    return $this->belongsTo(
      Task::class,
      'proofhub_task_id',
      'proofhub_task_id'
    );
  }

  /**
   * The "booted" method of the model.
   *
   * Defines model event listeners.
   *
   * @return void
   */
  protected static function booted()
  {
    static::deleting(function ($timeEntry) {
      // Additional logic if needed
    });

    static::created(function ($timeEntry) {
      // Additional logic if needed
    });
  }
}
