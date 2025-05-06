<?php

declare(strict_types=1);

namespace App\Actions\Notification;

use App\Models\Setting;
use App\Models\User;
use App\Notifications\ApiDownWarning;
use App\Notifications\LeaveReminderNotification;
use App\Notifications\ScheduleChangeNotification;
use App\Notifications\WeeklyUserReportNotification;
use App\Notifications\WelcomeEmail;
use Illuminate\Notifications\Notification;

class ShouldDeliverNotificationToUserAction
{
    /**
     * Centralized check to determine if a user should receive a specific notification.
     *
     * This method considers:
     * 1. Global notification enablement & user's master mute (via CheckGlobalUserPermission action).
     * 2. System-wide toggle for the specific notification type (if applicable).
     * 3. User's individual preference for the specific notification type (if applicable).
     *
     * @param  User  $user  The user instance.
     * @param  Notification  $notification  The notification instance to be sent.
     * @return bool True if the user should receive the notification, false otherwise.
     */
    public function handle(User $user, Notification $notification): bool
    {
        // Step 1 & 2: Check global enable and user master mute using the dedicated action.
        $globalPermissionAction = new CanUserReceiveAnyNotificationAction;
        if (! $globalPermissionAction->handle($user)) {
            return false;
        }

        // Step 3: Check system-wide toggles for specific notification types.
        if ($notification instanceof WelcomeEmail) {
            if (
                ! (bool) Setting::getValue('notification.welcome_email.enabled', true)
            ) {
                return false; // Welcome emails are globally disabled.
            }
        }

        if ($notification instanceof ApiDownWarning) {
            if (
                ! (bool) Setting::getValue(
                    'notification.api_down_warning_mail.enabled',
                    true
                )
            ) {
                return false; // API down warnings are globally disabled.
            }
            // Check user's individual preference if they are an admin
            if ($user->is_admin && ! ($user->notificationPreferences->api_down_warning ?? true)) {
                return false; // Admin user opted out of this specific notification.
            }
        }

        // Add checks for other system-wide toggles here...

        // Step 4: Check user's individual preferences for specific notification types.
        $preferences = $user->notificationPreferences; // Uses withDefault() ensuring it's an object

        // Check for ScheduleChangeNotification preference:
        if ($notification instanceof ScheduleChangeNotification) {
            // Check system-wide toggle first
            if (! (bool) Setting::getValue('notification.schedule_change.enabled', true)) {
                return false; // Globally disabled by admin
            }
            // Then check user preference
            if (! $preferences->schedule_change) {
                return false; // User opted out.
            }
        }

        // Check for WeeklyUserReportNotification preference:
        if ($notification instanceof WeeklyUserReportNotification) {
            // Check system-wide toggle first
            if (! (bool) Setting::getValue('notification.weekly_user_report.enabled', true)) {
                return false; // Globally disabled by admin
            }
            // Then check user preference
            if (! $preferences->weekly_user_report) {
                return false; // User opted out.
            }
        }

        // Check for LeaveReminderNotification preference:
        if ($notification instanceof LeaveReminderNotification) {
            // Check system-wide toggle first
            if (! (bool) Setting::getValue('notification.leave_reminder.enabled', true)) {
                return false; // Globally disabled by admin
            }
            // Then check user preference
            if (! $preferences->leave_reminder) {
                return false; // User opted out.
            }
        }

        // If no specific rule prevented it, the user can receive the notification.
        return true;
    }
}
