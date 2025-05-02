<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class LeaveType
 *
 * Represents leave types synchronized from Odoo.
 *
 * @property int $odoo_leave_type_id
 * @property string $name
 * @property bool $limit
 * @property bool $requires_allocation
 * @property bool $active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $validation_type
 * @property string|null $request_unit
 * @property bool $is_unpaid
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserLeave> $leaves
 * @property-read int|null $leaves_count
 *
 * @method static \Database\Factories\LeaveTypeFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveType query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveType whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveType whereIsUnpaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveType whereLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveType whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveType whereOdooLeaveTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveType whereRequestUnit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveType whereRequiresAllocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveType whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveType whereValidationType($value)
 *
 * @mixin \Eloquent
 */
class LeaveType extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'leave_types';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'odoo_leave_type_id';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the primary key.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'odoo_leave_type_id',
        'name',
        'validation_type',
        'request_unit',
        'limit',
        'requires_allocation',
        'active',
        'is_unpaid',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'odoo_leave_type_id' => 'integer',
        'limit' => 'boolean',
        'requires_allocation' => 'boolean',
        'active' => 'boolean',
        'is_unpaid' => 'boolean',
    ];

    /**
     * Get the leave records associated with the leave type.
     */
    public function leaves(): HasMany
    {
        return $this->hasMany(
            UserLeave::class,
            'leave_type_id',
            'odoo_leave_type_id'
        );
    }
}
