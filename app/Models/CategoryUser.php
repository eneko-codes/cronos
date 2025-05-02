<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Class CategoryUser
 *
 * Represents the pivot model between Category and User.
 *
 * @property int $user_id
 * @property int $category_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CategoryUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CategoryUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CategoryUser query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CategoryUser whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CategoryUser whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CategoryUser whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CategoryUser whereUserId($value)
 *
 * @mixin \Eloquent
 */
class CategoryUser extends Pivot
{
    /**
     * The table associated with the pivot model.
     *
     * @var string
     */
    protected $table = 'category_user';

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
        return $this->user_id.'-'.$this->category_id;
    }

    /**
     * Indicates if the model should manage timestamps.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['user_id', 'category_id'];
}
