<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class JobBatch
 *
 * Represents a batch of jobs to be processed.
 *
 * @property string $id
 * @property string $name
 * @property int $total_jobs
 * @property int $pending_jobs
 * @property int $failed_jobs
 * @property array $failed_job_ids
 * @property array $options
 * @property Carbon|null $cancelled_at
 * @property Carbon $created_at
 * @property Carbon|null $finished_at
 */
class JobBatch extends Model
{
  /**
   * The table associated with the model.
   *
   * @var string
   */
  protected $table = 'job_batches';

  /**
   * The data type of the primary key.
   *
   * @var string
   */
  protected $keyType = 'string';

  /**
   * Indicates if the IDs are auto-incrementing.
   *
   * @var bool
   */
  public $incrementing = false;

  /**
   * Indicates if the model should manage timestamps.
   *
   * @var bool
   */
  public $timestamps = false;

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = [
    'id',
    'name',
    'total_jobs',
    'pending_jobs',
    'failed_jobs',
    'failed_job_ids',
    'options',
    'cancelled_at',
    'created_at',
    'finished_at',
  ];

  /**
   * The attributes that should be cast to native types.
   *
   * @var array
   */
  protected $casts = [
    'failed_job_ids' => 'array',
    'options' => 'array',
    'created_at' => 'integer',
    'finished_at' => 'integer',
    'cancelled_at' => 'integer',
  ];

  /**
   * Get the created_at attribute as a Carbon instance.
   *
   * @param int $value
   * @return Carbon
   */
  public function getCreatedAtAttribute($value): Carbon
  {
    return Carbon::createFromTimestamp($value);
  }

  /**
   * Get the finished_at attribute as a Carbon instance or null.
   *
   * @param int|null $value
   * @return Carbon|null
   */
  public function getFinishedAtAttribute($value): ?Carbon
  {
    return $value ? Carbon::createFromTimestamp($value) : null;
  }
}
