<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Notifications\DatabaseNotification as BaseDatabaseNotification;

/**
 * Custom DatabaseNotification model that extends Laravel's notification model
 * to add automatic pruning of old notifications.
 *
 * The retention period is configurable via the 'notifications.retention_period'
 * setting in the database (default: 30 days).
 */
class DatabaseNotification extends BaseDatabaseNotification
{
    use MassPrunable;

    /**
     * Get the prunable model query.
     * Prunes notifications older than the configured retention period.
     */
    public function prunable(): Builder
    {
        $retentionDays = (int) Setting::getValue('notifications.retention_period', 30);

        return static::where('created_at', '<=', now()->subDays($retentionDays));
    }
}
