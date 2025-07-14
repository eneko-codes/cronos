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
 * @property string|null $request_unit
 * @property bool $active
 * @property string|null $odoo_created_at
 * @property string|null $odoo_updated_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property bool $limit
 * @property bool $is_unpaid
 * @property string|null $create_date
 * @property string|null $write_date
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveType whereUpdatedAt($value)
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
     * @var list<string>
     */
    protected $fillable = [
        'odoo_leave_type_id',
        'name',
        'request_unit',
        'active',
        'odoo_created_at',
        'odoo_updated_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'odoo_leave_type_id' => 'integer',
        'name' => 'string',
        'request_unit' => 'string',
        'active' => 'boolean',
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
