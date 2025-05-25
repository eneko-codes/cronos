<?php

namespace App\Enums;

use App\Models\User;

enum NotificationType: string
{
    case ScheduleChange = 'schedule_change';
    case WeeklyUserReport = 'weekly_user_report';
    case LeaveReminder = 'leave_reminder';
    case ApiDownWarning = 'api_down_warning';
    case AdminPromotionEmail = 'admin_promotion_email';
    case WelcomeEmail = 'welcome_email';
    case UserPromotedToAdmin = 'user_promoted_to_admin';
    case DuplicateScheduleWarning = 'duplicate_schedule_warning';

    /**
     * Get the human-readable label for this notification type
     */
    public function label(): string
    {
        return match ($this) {
            self::ScheduleChange => 'Schedule Change',
            self::WeeklyUserReport => 'Weekly User Report',
            self::LeaveReminder => 'Leave Reminder',
            self::ApiDownWarning => 'API Down Warning',
            self::AdminPromotionEmail => 'Admin Promotion Email',
            self::WelcomeEmail => 'Welcome Email',
            self::UserPromotedToAdmin => 'User Promoted To Admin',
            self::DuplicateScheduleWarning => 'Duplicate Schedule Warning',
        };
    }

    /**
     * Check if this notification type is admin-only
     */
    public function isAdminOnly(): bool
    {
        return match ($this) {
            self::ApiDownWarning,
            self::AdminPromotionEmail,
            self::UserPromotedToAdmin,
            self::DuplicateScheduleWarning => true,
            default => false,
        };
    }

    /**
     * Get the default enabled state for this notification type
     */
    public function defaultEnabled(): bool
    {
        return match ($this) {
            self::ApiDownWarning => false, // Only one that defaults to false
            default => true,
        };
    }

    /**
     * Get the description for this notification type
     */
    public function description(): string
    {
        return match ($this) {
            self::ScheduleChange => 'Notifications when your work schedule is updated',
            self::WeeklyUserReport => 'Weekly summary of your activity and time tracking',
            self::LeaveReminder => 'Reminders about upcoming time off',
            self::ApiDownWarning => 'Alerts when external services are experiencing issues',
            self::AdminPromotionEmail => 'Notifications when users are promoted to admin',
            self::WelcomeEmail => 'Welcome messages for new users',
            self::UserPromotedToAdmin => 'Notifications when you are promoted to admin',
            self::DuplicateScheduleWarning => 'Warnings about conflicting schedule assignments',
        };
    }

    /**
     * Get all notification types available to a specific user
     */
    public static function availableForUser(?User $user = null): array
    {
        $types = [];
        foreach (self::cases() as $type) {
            if (! $type->isAdminOnly() || ($user && $user->isAdmin())) {
                $types[] = $type;
            }
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
                'default_enabled' => $type->defaultEnabled(),
            ];
        }

        return $config;
    }
}
