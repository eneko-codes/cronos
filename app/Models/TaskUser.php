<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Class TaskUser
 *
 * Represents the pivot model between Task and User.
 *
 * @property int $user_id
 * @property int $proofhub_task_id
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskUser query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskUser whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskUser whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskUser whereProofhubTaskId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskUser whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskUser whereUserId($value)
 *
 * @mixin \Eloquent
 */
class TaskUser extends Pivot
{
    /**
     * The table associated with the pivot model.
     *
     * @var string
     */
    protected $table = 'task_user';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['user_id', 'proofhub_task_id'];

    /**
     * Indicates if the primary key is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Get the value of the model's primary key.
     *
     * @return string
     */
    public function getKey()
    {
        return $this->user_id.'-'.$this->proofhub_task_id;
    }

    /**
     * Indicates if the model should manage timestamps.
     *
     * @var bool
     */
    public $timestamps = true;
}
