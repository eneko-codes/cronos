<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Notification types for the application.
 *
 * Notification types are organized into groups (Personal, Maintenance, Admin)
 * for better UI organization. Use the group() method to get the group for a type,
 * or use the static grouped()/groupedForUser() methods to get all types organized by group.
 */
enum NotificationType: string
{
    // Regular user notifications (most common/frequent)
    case ScheduleChange = 'schedule_change';
    case LeaveReminder = 'leave_reminder';

    // System/technical notifications
    case ApiDown = 'api_down';
    case UnlinkedPlatformUser = 'unlinked_platform_user';

    // Admin notifications - personal notification first
    case UserPromotedToAdmin = 'user_promoted_to_admin';

    // Maintenance notifications - personal notification
    case UserPromotedToMaintenance = 'user_promoted_to_maintenance';

    // Admin notifications - about others (grouped by action type)
    case AdminPromotion = 'admin_promotion';
    case AdminDemotion = 'admin_demotion';
    case MaintenancePromotion = 'maintenance_promotion';
    case MaintenanceDemotion = 'maintenance_demotion';

    // Always sent (cannot be disabled)
    case WelcomeNewUser = 'welcome_new_user';

    /**
     * Get the human-readable label for this notification type
     */
    public function label(): string
    {
        return match ($this) {
            self::ScheduleChange => 'Schedule Change',
            self::LeaveReminder => 'Leave Reminder',
            self::ApiDown => 'API Down Warning',
            self::UnlinkedPlatformUser => 'Unlinked Platform User',
            self::AdminPromotion => 'Admin Promotion (to Admins)',
            self::AdminDemotion => 'Admin Demotion (to Admins)',
            self::MaintenancePromotion => 'Maintenance Promotion (to Admins)',
            self::MaintenanceDemotion => 'Maintenance Demotion (to Admins)',
            self::WelcomeNewUser => 'Welcome Email',
            self::UserPromotedToAdmin => 'You Promoted To Admin',
            self::UserPromotedToMaintenance => 'You Promoted To Maintenance',
        };
    }

    /**
     * Check if this notification type is admin-only
     */
    public function isAdminOnly(): bool
    {
        return match ($this) {
            self::AdminPromotion,
            self::AdminDemotion,
            self::MaintenancePromotion,
            self::MaintenanceDemotion,
            self::UserPromotedToAdmin => true,
            default => false,
        };
    }

    /**
     * Check if this notification type is maintenance-only
     */
    public function isMaintenanceOnly(): bool
    {
        return match ($this) {
            self::ApiDown,
            self::UnlinkedPlatformUser,
            self::UserPromotedToMaintenance => true,
            default => false,
        };
    }

    /**
     * Get the default enabled state for this notification type
     */
    public function defaultEnabled(): bool
    {
        return match ($this) {
            self::ScheduleChange => true,
            self::LeaveReminder => true,
            self::ApiDown => true,
            self::UnlinkedPlatformUser => true,
            self::AdminPromotion => true,
            self::AdminDemotion => true,
            self::MaintenancePromotion => true,
            self::MaintenanceDemotion => true,
            self::WelcomeNewUser => true,
            self::UserPromotedToAdmin => true,
            self::UserPromotedToMaintenance => true,
        };
    }

    /**
     * Get the description for this notification type
     */
    public function description(): string
    {
        return match ($this) {
            self::ScheduleChange => 'Notifications when your work schedule is updated',
            self::LeaveReminder => 'Reminders about upcoming time off',
            self::ApiDown => 'Alerts when external services are experiencing issues',
            self::UnlinkedPlatformUser => 'Alerts when a platform user cannot be automatically linked to a local user',
            self::AdminPromotion => 'Notifications sent to admins when other users are promoted to admin',
            self::AdminDemotion => 'Notifications sent to admins when other users are demoted from admin',
            self::MaintenancePromotion => 'Notifications sent to admins when other users are promoted to maintenance',
            self::MaintenanceDemotion => 'Notifications sent to admins when other users are removed from maintenance',
            self::WelcomeNewUser => 'Welcome messages for new users',
            self::UserPromotedToAdmin => 'Personal notification sent to you when you are promoted to admin',
            self::UserPromotedToMaintenance => 'Personal notification sent to you when you are promoted to maintenance',
        };
    }

    /**
     * Check if this notification type can be disabled globally.
     *
     * Some notifications (like WelcomeNewUser for new users) must always be sent
     * and cannot be disabled via global preferences.
     */
    public function canBeDisabledGlobally(): bool
    {
        return match ($this) {
            self::WelcomeNewUser => false, // WelcomeNewUserNotification must always send for password setup
            default => true,
        };
    }

    /**
     * Get all notification types available to a specific user
     */
    public static function availableForUser(?User $user = null): array
    {
        $types = [];
        foreach (self::cases() as $type) {
            // Admin-only notifications: only show to admins
            if ($type->isAdminOnly() && (! $user || ! $user->isAdmin())) {
                continue;
            }
            // Maintenance-only notifications: only show to maintenance users
            if ($type->isMaintenanceOnly() && (! $user || ! $user->isMaintenance())) {
                continue;
            }
            $types[] = $type;
        }

        return $types;
    }

    /**
     * Get all notification types as an array for seeding/configuration
     */
    public static function toConfigArray(): array
    {
        $config = [];
        foreach (self::cases() as $type) {
            $config[$type->value] = [
                'label' => $type->label(),
                'description' => $type->description(),
                'admin_only' => $type->isAdminOnly(),
                'maintenance_only' => $type->isMaintenanceOnly(),
                'default_enabled' => $type->defaultEnabled(),
            ];
        }

        return $config;
    }

    /**
     * Get the group this notification type belongs to.
     *
     * Groups organize notifications by role/category for better UI organization:
     * - Personal: Universal notifications for all users
     * - Maintenance: Technical alerts for maintenance role users
     * - Admin: Administrative notifications for admin role users
     */
    public function group(): NotificationGroup
    {
        return match (true) {
            $this->isAdminOnly() => NotificationGroup::Admin,
            $this->isMaintenanceOnly() => NotificationGroup::Maintenance,
            default => NotificationGroup::Personal,
        };
    }

    /**
     * Get all notification types grouped by their category.
     *
     * @return Collection<string, Collection<int, NotificationType>>
     */
    public static function grouped(): Collection
    {
        return collect(self::cases())
            ->groupBy(fn (NotificationType $type) => $type->group()->value)
            ->sortBy(fn (Collection $types, string $groupKey) => NotificationGroup::from($groupKey)->order());
    }

    /**
     * Get all notification types available to a user, grouped by category.
     *
     * @param  User|null  $user  The user to filter for (null returns all types)
     * @return Collection<string, Collection<int, NotificationType>>
     */
    public static function groupedForUser(?User $user = null): Collection
    {
        return collect(self::availableForUser($user))
            ->groupBy(fn (NotificationType $type) => $type->group()->value)
            ->sortBy(fn (Collection $types, string $groupKey) => NotificationGroup::from($groupKey)->order());
    }
}
