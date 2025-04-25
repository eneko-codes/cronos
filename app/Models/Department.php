<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Department Model
 *
 * Represents organizational departments synchronized from Odoo's hr.department records.
 * Departments are used for grouping employees and can be targets for department-wide
 * leaves and scheduling. They are a fundamental organizational unit for employee management.
 *
 * @property int $odoo_department_id Primary key (from Odoo, not auto-incremented)
 * @property string $name Human-readable department name (e.g., "Engineering", "Marketing")
 * @property bool $active Whether the department is currently active
 * @property \Carbon\Carbon|null $created_at When record was created locally
 * @property \Carbon\Carbon|null $updated_at When record was last updated locally
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\User[] $users Employees in this department
 */
class Department extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'departments';

    /**
     * The primary key for the model.
     *
     * Using odoo_department_id as the primary key to maintain direct mapping with Odoo.
     * This ensures consistency between the local database and the external system.
     *
     * @var string
     */
    protected $primaryKey = 'odoo_department_id';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * Set to false because department IDs come from Odoo and are not generated locally.
     * This prevents Laravel from trying to auto-increment the IDs during creation.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the primary key.
     *
     * Defined explicitly as integer to match Odoo's ID type for departments.
     * This ensures proper type handling in database operations.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['odoo_department_id', 'name', 'active'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = ['active' => 'boolean'];

    /**
     * Get the users associated with the department.
     *
     * Retrieves all employees that belong to this department.
     * Note: Uses odoo_department_id as the foreign key to maintain
     * direct mapping with Odoo identifiers.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'department_id', 'odoo_department_id');
    }
}
