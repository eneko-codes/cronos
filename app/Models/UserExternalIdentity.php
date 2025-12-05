<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Platform;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a mapping between a local user and their identity on an external platform.
 *
 * This model enables users to have different emails across platforms while still
 * being correctly identified. Supports both automatic (email-based) and manual linking.
 *
 * @property int $id
 * @property int $user_id
 * @property Platform $platform
 * @property string $external_id
 * @property string|null $external_email
 * @property bool $is_manual_link
 * @property string|null $linked_by
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read User $user
 */
class UserExternalIdentity extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_external_identities';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'platform',
        'external_id',
        'external_email',
        'is_manual_link',
        'linked_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'is_manual_link' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns this external identity.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
