<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
     * Get the duration between effective_from and effective_until dates.
     *
     * This accessor calculates the duration of the schedule assignment
     * and returns it in a human-readable format.
     */
    protected function duration(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->effective_until
                ? $this->effective_from->diffForHumans($this->effective_until, CarbonInterface::DIFF_ABSOLUTE)
                : null
        );
    }

    /**
     * Scope a query to only include currently active schedule assignments.
     *
     * Active assignments are those without an end date (effective_until is null).
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('effective_until');
    }

    /**
     * Scope a query to only include schedule assignments effective at a given date.
     *
     * This includes assignments that are either:
     * - Still active (no end date)
     * - Have an end date on or after the given date
     */
    #[Scope]
    protected function effectiveAt(Builder $query, CarbonInterface|string $date): void
    {
        $query->where(function ($q) use ($date): void {
            $q->whereNull('effective_until')
                ->orWhere('effective_until', '>=', $date);
        });
    }
}
