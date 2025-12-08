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
    case ApiDownWarning = 'api_down_warning';
    case UnlinkedPlatformUser = 'unlinked_platform_user';

    // Admin notifications - personal notification first
    case UserPromotedToAdmin = 'user_promoted_to_admin';

    // Maintenance notifications - personal notification
    case UserPromotedToMaintenance = 'user_promoted_to_maintenance';

    // Admin notifications - about others (grouped by action type)
    case AdminPromotionEmail = 'admin_promotion_email';
    case AdminDemotionEmail = 'admin_demotion_email';
    case MaintenancePromotionEmail = 'maintenance_promotion_email';
    case MaintenanceDemotionEmail = 'maintenance_demotion_email';

    // Always sent (cannot be disabled)
    case WelcomeEmail = 'welcome_email';

    /**
     * Get the human-readable label for this notification type
     */
    public function label(): string
    {
        return match ($this) {
            self::ScheduleChange => 'Schedule Change',
            self::LeaveReminder => 'Leave Reminder',
            self::ApiDownWarning => 'API Down Warning',
            self::UnlinkedPlatformUser => 'Unlinked Platform User',
            self::AdminPromotionEmail => 'Admin Promotion (to Admins)',
            self::AdminDemotionEmail => 'Admin Demotion (to Admins)',
            self::MaintenancePromotionEmail => 'Maintenance Promotion (to Admins)',
            self::MaintenanceDemotionEmail => 'Maintenance Demotion (to Admins)',
            self::WelcomeEmail => 'Welcome Email',
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
            self::AdminPromotionEmail,
            self::AdminDemotionEmail,
            self::MaintenancePromotionEmail,
            self::MaintenanceDemotionEmail,
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
            self::ApiDownWarning,
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
            self::ApiDownWarning => true,
            self::UnlinkedPlatformUser => true,
            self::AdminPromotionEmail => true,
            self::AdminDemotionEmail => true,
            self::MaintenancePromotionEmail => true,
            self::MaintenanceDemotionEmail => true,
            self::WelcomeEmail => true,
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
            self::ApiDownWarning => 'Alerts when external services are experiencing issues',
            self::UnlinkedPlatformUser => 'Alerts when a platform user cannot be automatically linked to a local user',
            self::AdminPromotionEmail => 'Notifications sent to admins when other users are promoted to admin',
            self::AdminDemotionEmail => 'Notifications sent to admins when other users are demoted from admin',
            self::MaintenancePromotionEmail => 'Notifications sent to admins when other users are promoted to maintenance',
            self::MaintenanceDemotionEmail => 'Notifications sent to admins when other users are removed from maintenance',
            self::WelcomeEmail => 'Welcome messages for new users',
            self::UserPromotedToAdmin => 'Personal notification sent to you when you are promoted to admin',
            self::UserPromotedToMaintenance => 'Personal notification sent to you when you are promoted to maintenance',
        };
    }

    /**
     * Check if this notification type can be disabled globally.
     *
     * Some notifications (like WelcomeEmail for new users) must always be sent
     * and cannot be disabled via global preferences.
     */
    public function canBeDisabledGlobally(): bool
    {
        return match ($this) {
            self::WelcomeEmail => false, // WelcomeNewUserEmail must always send for password setup
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
