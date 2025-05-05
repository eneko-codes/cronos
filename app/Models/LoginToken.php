<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a single-use token for passwordless (magic link) authentication.
 *
 * @property int $user_id Foreign key linking to the users table.
 * @property string $token The SHA-256 hash of the actual token sent to the user.
 * @property \Carbon\Carbon $expires_at The timestamp when this token becomes invalid.
 * @property bool $remember Indicates if the user requested a persistent session (Remember Me).
 * @property \Illuminate\Support\Carbon|null $created_at Timestamp of creation.
 * @property \Illuminate\Support\Carbon|null $updated_at Timestamp of last update.
 * @property-read User $user The user associated with this token.
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
     * @var list<string>
     */
    protected $fillable = ['user_id', 'token', 'expires_at', 'remember'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'remember' => 'boolean',
    ];

    /**
     * Defines the relationship to the User model.
     *
     * @return BelongsTo The relationship instance.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
