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
    case ScheduleStarting = 'schedule_starting';
    case LeaveReminder = 'leave_reminder';
    case LeaveStatusChange = 'leave_status_change';

    // System/technical notifications
    case ApiDown = 'api_down';
    case UnlinkedPlatformUser = 'unlinked_platform_user';
    case FailedLoginAttempt = 'failed_login_attempt';

    // Admin notifications - about others (grouped by action type)
    case AdminPromotion = 'admin_promotion';
    case AdminDemotion = 'admin_demotion';
    case MaintenancePromotion = 'maintenance_promotion';
    case MaintenanceDemotion = 'maintenance_demotion';
    case UserArchivedAdmin = 'user_archived_admin';
    case UserReactivatedAdmin = 'user_reactivated_admin';
    case UserDoNotTrackAdmin = 'user_do_not_track_admin';
    case UserTrackingEnabledAdmin = 'user_tracking_enabled_admin';

    // Always sent (cannot be disabled - critical security/legal/personal notifications)
    case WelcomeNewUser = 'welcome_new_user';
    case UserPromotedToAdmin = 'user_promoted_to_admin';
    case UserPromotedToMaintenance = 'user_promoted_to_maintenance';
    case UserDemotedFromAdmin = 'user_demoted_from_admin';
    case UserDemotedFromMaintenance = 'user_demoted_from_maintenance';
    case UserArchived = 'user_archived';
    case UserReactivated = 'user_reactivated';
    case UserDoNotTrack = 'user_do_not_track';
    case UserTrackingEnabled = 'user_tracking_enabled';
    case AccountLockout = 'account_lockout';

    /**
     * Get the human-readable label for this notification type
     */
    public function label(): string
    {
        return match ($this) {
            self::ScheduleChange => 'Schedule Updated',
            self::ScheduleStarting => 'New Schedule Starts',
            self::LeaveReminder => 'Upcoming Leave',
            self::LeaveStatusChange => 'Leave Request Update',
            self::ApiDown => 'Service Outage',
            self::UnlinkedPlatformUser => 'User Matching Issue',
            self::FailedLoginAttempt => 'Failed Login Attempts',
            self::AdminPromotion => 'User Promoted to Admin',
            self::AdminDemotion => 'User Removed from Admin',
            self::MaintenancePromotion => 'User Promoted to Maintenance',
            self::MaintenanceDemotion => 'User Removed from Maintenance',
            self::UserArchivedAdmin => 'User Archived',
            self::UserReactivatedAdmin => 'User Reactivated',
            self::UserDoNotTrackAdmin => 'User Tracking Disabled',
            self::UserTrackingEnabledAdmin => 'User Tracking Enabled',
            self::WelcomeNewUser => 'Welcome Email',
            self::UserPromotedToAdmin => 'You\'re Now an Admin',
            self::UserPromotedToMaintenance => 'You\'re Now Maintenance',
            self::UserDemotedFromAdmin => 'You\'re No Longer an Admin',
            self::UserDemotedFromMaintenance => 'You\'re No Longer Maintenance',
            self::UserArchived => 'Your Account Was Archived',
            self::UserReactivated => 'Your Account Was Reactivated',
            self::UserDoNotTrack => 'Tracking Disabled',
            self::UserTrackingEnabled => 'Tracking Re-enabled',
            self::AccountLockout => 'Account Temporarily Locked',
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
            self::UserPromotedToAdmin,
            self::UserDemotedFromAdmin,
            self::UserArchivedAdmin,
            self::UserReactivatedAdmin,
            self::UserDoNotTrackAdmin,
            self::UserTrackingEnabledAdmin => true,
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
            self::FailedLoginAttempt,
            self::UserPromotedToMaintenance,
            self::UserDemotedFromMaintenance => true,
            default => false,
        };
    }

    /**
     * Get the default enabled state for this notification type.
     *
     * All notifications are enabled by default.
     * Users and admins can opt-out of specific notifications as needed.
     */
    public function defaultEnabled(): bool
    {
        return true;
    }

    /**
     * Get the description for this notification type
     */
    public function description(): string
    {
        return match ($this) {
            self::ScheduleChange => 'Get notified when your current work schedule assignment ends',
            self::ScheduleStarting => 'Get notified when a new work schedule assignment begins',
            self::LeaveReminder => 'Receive reminders before your approved leave starts',
            self::LeaveStatusChange => 'Get notified when your leave request is approved, rejected, or cancelled',
            self::ApiDown => 'Get alerts when external services (Odoo, ProofHub, etc.) are experiencing issues',
            self::UnlinkedPlatformUser => 'Get alerts when users from external platforms cannot be automatically matched',
            self::FailedLoginAttempt => 'Get security alerts when multiple failed login attempts are detected',
            self::AdminPromotion => 'Get notified when someone is promoted to administrator',
            self::AdminDemotion => 'Get notified when someone is removed from administrator role',
            self::MaintenancePromotion => 'Get notified when someone is promoted to maintenance role',
            self::MaintenanceDemotion => 'Get notified when someone is removed from maintenance role',
            self::WelcomeNewUser => 'Welcome email sent to new users with password setup instructions',
            self::UserArchivedAdmin => 'Get notified when a user account is archived and their data is deleted',
            self::UserReactivatedAdmin => 'Get notified when a previously archived user account is reactivated',
            self::UserDoNotTrackAdmin => 'Get notified when a user opts out of tracking and their data is removed',
            self::UserTrackingEnabledAdmin => 'Get notified when tracking is re-enabled for a user',
            self::UserPromotedToAdmin => 'Get notified when you are promoted to administrator role',
            self::UserPromotedToMaintenance => 'Get notified when you are promoted to maintenance role',
            self::UserDemotedFromAdmin => 'Get notified when you are removed from administrator role',
            self::UserDemotedFromMaintenance => 'Get notified when you are removed from maintenance role',
            self::UserArchived => 'Get notified when your account is archived and you lose access',
            self::UserReactivated => 'Get notified when your account is reactivated and access is restored',
            self::UserDoNotTrack => 'Get notified when tracking is disabled for your account and your data is removed',
            self::UserTrackingEnabled => 'Get notified when tracking is re-enabled for your account',
            self::AccountLockout => 'Get notified when your account is temporarily locked due to too many failed login attempts',
        };
    }

    /**
     * Check if this notification is mandatory (always sends, cannot be disabled).
     *
     * Mandatory notifications:
     * - Always send via email only (no in-app, no slack)
     * - Bypass all global and user preferences
     * - Cannot be disabled by admins or users
     *
     * Use cases: Welcome emails, security alerts, important account changes.
     */
    public function isMandatory(): bool
    {
        return match ($this) {
            self::WelcomeNewUser,       // Password setup - required for account access
            self::UserArchived,         // Account archived - important personal notification
            self::UserReactivated,      // Account reactivated - important personal notification
            self::UserDoNotTrack,       // Privacy change - legal/compliance requirement
            self::UserTrackingEnabled,  // Privacy change - legal/compliance requirement
            self::AccountLockout,       // Security alert - user must be informed
            self::UserPromotedToAdmin,  // Role change - user must know their new permissions
            self::UserPromotedToMaintenance, // Role change - user must know their new permissions
            self::UserDemotedFromAdmin, // Role change - user must know their lost permissions
            self::UserDemotedFromMaintenance => true, // Role change - user must know their lost permissions
            default => false,
        };
    }

    /**
     * Get all notification types available to a specific user
     */
    public static function availableForUser(?User $user = null): array
    {
        return collect(self::cases())
            ->filter(function (NotificationType $type) use ($user): bool {
                // Admin-only notifications: only show to admins
                if ($type->isAdminOnly() && (! $user || ! $user->isAdmin())) {
                    return false;
                }
                // Maintenance-only notifications: only show to maintenance users
                if ($type->isMaintenanceOnly() && (! $user || ! $user->isMaintenance())) {
                    return false;
                }

                return true;
            })
            ->values()
            ->all();
    }

    /**
     * Get all notification types as an array for seeding/configuration
     */
    public static function toConfigArray(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (NotificationType $type) => [
                $type->value => [
                    'label' => $type->label(),
                    'description' => $type->description(),
                    'admin_only' => $type->isAdminOnly(),
                    'maintenance_only' => $type->isMaintenanceOnly(),
                    'default_enabled' => $type->defaultEnabled(),
                ],
            ])
            ->all();
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
     * Get all notification types available to a user, grouped by category.
     *
     * @param  User|null  $user  The user to filter for (null returns all types)
     * @return Collection<string, Collection<int, NotificationType>>
     */
    public static function groupedForUser(?User $user = null): Collection
    {
        /** @var Collection<string, Collection<int, NotificationType>> $grouped */
        $grouped = collect(self::availableForUser($user))
            ->groupBy(fn (NotificationType $type) => $type->group()->value)
            ->sortBy(fn (Collection $types, string $groupKey) => NotificationGroup::from($groupKey)->order());

        return $grouped;
    }
}
