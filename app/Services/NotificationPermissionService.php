<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use App\Notifications\ApiDownWarning;
use App\Notifications\LeaveReminderNotification;
use App\Notifications\ScheduleChangeNotification;
use App\Notifications\WeeklyUserReportNotification;
use App\Notifications\WelcomeEmail;
use Illuminate\Notifications\Notification;

class NotificationPermissionService
{
    /**
     * Check if notifications are generally enabled for the user.
     *
     * This checks global settings and the user's master mute preference.
     *
     * @return bool Returns false if notifications are globally disabled or user muted all, true otherwise.
     */
    public function shouldUserReceiveAnyNotification(User $user): bool
    {
        // First, check if notifications are globally disabled by an administrator.
        $isGloballyEnabled = (bool) Setting::getValue(
            'notifications.global_enabled',
            true
        ); // Default to true if setting doesn't exist
        if (! $isGloballyEnabled) {
            return false; // Globally disabled.
        }

        // Next, check the user's specific preference to mute all their notifications.
        $preferences = $user->notificationPreferences; // Relies on withDefault()

        // If preferences record exists and mute_all is true, user has opted out.
        if ($preferences && $preferences->mute_all) {
            return false;
        }

        // If globally enabled and user hasn't muted all, they might receive notifications.
        return true;
    }

    /**
     * Centralized check to determine if a user should receive a specific notification.
     *
     * This method considers:
     * 1. Global notification enablement.
     * 2. User's master mute preference.
     * 3. System-wide toggle for the specific notification type (if applicable).
     * 4. User's individual preference for the specific notification type (if applicable).
     *
     * @param  User  $user  The user instance.
     * @param  Notification  $notification  The notification instance to be sent.
     * @return bool True if the user should receive the notification, false otherwise.
     */
    public function canUserReceiveNotification(User $user, Notification $notification): bool
    {
        // Step 1 & 2: Check global enable and user master mute.
        if (! $this->shouldUserReceiveAnyNotification($user)) {
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
        }

        // Add checks for other system-wide toggles here...

        // Step 4: Check user's individual preferences for specific notification types.
        $preferences = $user->notificationPreferences; // Uses withDefault() ensuring it's an object

        // Check for ScheduleChangeNotification preference:
        if ($notification instanceof ScheduleChangeNotification) {
            if (
                property_exists($preferences, 'schedule_change') &&
                ! $preferences->schedule_change
            ) {
                return false; // User opted out.
            }
        }

        // Check for WeeklyUserReportNotification preference:
        if ($notification instanceof WeeklyUserReportNotification) {
            if (
                property_exists($preferences, 'weekly_user_report') &&
                ! $preferences->weekly_user_report
            ) {
                return false; // User opted out.
            }
        }

        // Check for LeaveReminderNotification preference:
        if ($notification instanceof LeaveReminderNotification) {
            if (
                property_exists($preferences, 'leave_reminder') &&
                ! $preferences->leave_reminder
            ) {
                return false; // User opted out.
            }
        }

        // Add checks for other user preferences here...

        // If no specific rule prevented it, the user can receive the notification.
        return true;
    }
}
