<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class LoginToken
 *
 * Represents a user's login token.
 *
 * @property string $id
 * @property int $user_id
 * @property string $token
 * @property \Carbon\Carbon $expires_at
 * @property bool $remember
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginToken newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginToken newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginToken query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginToken whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginToken whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginToken whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginToken whereRemember($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginToken whereToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginToken whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginToken whereUserId($value)
 *
 * @mixin \Eloquent
 */
class LoginToken extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'login_tokens';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['user_id', 'token', 'expires_at', 'remember'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'remember' => 'boolean',
    ];

    /**
     * Get the user that owns the login token.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
