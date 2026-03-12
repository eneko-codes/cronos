<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Notification groups for organizing notification types in the UI.
 *
 * Groups are displayed in the order defined by the order() method.
 * - Personal: Universal notifications for all users (schedule, leave)
 * - Maintenance: Technical alerts for maintenance role users
 * - Admin: Administrative notifications for admin role users
 */
enum NotificationGroup: string
{
    case Personal = 'personal';
    case Maintenance = 'maintenance';
    case Admin = 'admin';

    /**
     * Get the display label for this group.
     */
    public function label(): string
    {
        return match ($this) {
            self::Personal => 'Personal Notifications',
            self::Maintenance => 'Maintenance Role Notifications',
            self::Admin => 'Admin Role Notifications',
        };
    }

    /**
     * Get a description for this group.
     */
    public function description(): string
    {
        return match ($this) {
            self::Personal => 'Notifications about your schedule and time off',
            self::Maintenance => 'Technical alerts for system maintenance tasks',
            self::Admin => 'Administrative notifications about user management',
        };
    }

    /**
     * Get the display order for sorting groups.
     */
    public function order(): int
    {
        return match ($this) {
            self::Personal => 1,
            self::Maintenance => 2,
            self::Admin => 3,
        };
    }
}
