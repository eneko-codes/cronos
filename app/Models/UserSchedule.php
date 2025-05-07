<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class UserSchedule
 *
 * Represents an employee's schedule assignment history.
 *
 * @property int $id
 * @property int $user_id
 * @property int $odoo_schedule_id
 * @property \Illuminate\Support\Carbon $effective_from
 * @property \Illuminate\Support\Carbon|null $effective_until
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string|null $duration
 * @property-read \App\Models\Schedule $schedule
 * @property-read \App\Models\User $user
 *
 * @method static \Database\Factories\UserScheduleFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSchedule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSchedule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSchedule query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSchedule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSchedule whereEffectiveFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSchedule whereEffectiveUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSchedule whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSchedule whereOdooScheduleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSchedule whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserSchedule whereUserId($value)
 *
 * @mixin \Eloquent
 */
class UserSchedule extends Model
{
    use HasFactory;

    protected $table = 'user_schedules';

    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'odoo_schedule_id',
        'effective_from',
        'effective_until',
    ];

    protected $casts = [
        'effective_from' => 'datetime',
        'effective_until' => 'datetime',
    ];

    protected $appends = ['duration'];

    /**
     * The user that owns this schedule assignment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The schedule associated with this assignment.
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(
            Schedule::class,
            'odoo_schedule_id',
            'odoo_schedule_id'
        );
    }

    /**
     * Accessor for the duration attribute.
     */
    public function getDurationAttribute(): ?string
    {
        if ($this->effective_until) {
            return $this->effective_from->diffForHumans($this->effective_until, \Carbon\CarbonInterface::DIFF_ABSOLUTE);
        }

        return null;
    }
}
