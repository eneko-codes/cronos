# How Notifications Work

This document explains how each notification in the Cronos application works, including delivery channels, recipients, triggers, and purpose.

## Notification Channels

All notifications support multiple delivery channels based on the global `notification_channel` setting:

- **Database**: Always included for in-app notifications (stored in `notifications` table)
- **Mail**: Email notifications (default channel)
- **Slack**: Slack notifications (when `notification_channel` is set to `'slack'`)

The channel selection is controlled by the `Setting::getValue('notification_channel', 'mail')` configuration.

---

## Regular User Notifications

### Schedule Change Notification

**Type**: `NotificationType::ScheduleChange`  
**Channels**: `['database']` + `['mail']` or `['slack']` (based on global setting)  
**Queue**: Yes (`ShouldQueue`)  
**Eligibility**: Checked via `NotificationPreferenceService`

**Recipients**:

- The user whose schedule changed

**When Sent**:

- When a user's schedule assignment ends (`UserScheduleObserver::updated()`)
- Triggered when `effective_until` is set on a `UserSchedule` record
- Occurs during Odoo sync when user's schedule changes (`ProcessOdooUserAction::syncUserSchedule()`)

**Purpose**:

- Notifies users when their work schedule assignment ends
- Includes details about the previous schedule (description, days, times)
- Helps users stay informed about their work schedule changes

**Special Notes**:

- Currently only sent when a schedule ends (not when a new one starts)
- Notification includes old schedule details and indicates no new schedule

---

### Leave Reminder Notification

**Type**: `NotificationType::LeaveReminder`  
**Channels**: `['database']` + `['mail']` or `['slack']` (based on global setting)  
**Queue**: Yes (`ShouldQueue`)  
**Eligibility**: Checked via `NotificationPreferenceService`

**Recipients**:

- Users with approved upcoming leaves

**When Sent**:

- Scheduled job: `SendUserLeaveReminderJob` (default: 1 day in advance)
- For users with approved leaves starting on the target date

**Purpose**:

- Reminds users about upcoming time off
- Includes leave type, start date, end date, and duration
- Helps users prepare for their time away

**Special Notes**:

- Only sent for approved leaves (`status = 'approved'`)
- Skips users with `do_not_track = true`
- Configurable days in advance (default: 1 day)
- Job runs automatically via Laravel scheduler

---

## System/Technical Notifications

### API Down Warning

**Type**: `NotificationType::ApiDown`  
**Channels**: `['database']` + `['mail']` or `['slack']` (based on global setting)  
**Queue**: Yes (`ShouldQueue`)  
**Eligibility**: Checked via `NotificationPreferenceService` (Maintenance users only)

**Recipients**:

- All users with `user_type = RoleType::Maintenance`
- Only if they have enabled `ApiDown` notifications

**When Sent**:

- When SystemPin API health check fails (`CheckSystemPinHealthAction`)
- When Odoo API health check fails (`CheckOdooHealthAction`)
- When ProofHub API health check fails (`CheckProofhubHealthAction`)
- When DeskTime API health check fails (`CheckDesktimeHealthAction`)
- Typically triggered from sync job failure handlers

**Purpose**:

- Alerts maintenance team when external APIs are down
- Includes service name and error details
- Enables quick response to API issues

**Special Notes**:

- **Maintenance-only notification** - only visible to maintenance users
- **Deduplication logic**: Max 1 notification per 60-minute window per service per user
- Uses cache-based throttling with atomic locks
- Prevents spam when multiple sync jobs fail simultaneously
- Each service (SystemPin, Odoo, ProofHub, DeskTime) has separate throttle windows

---

## Personal Role Promotion Notifications

### User Promoted To Admin Notification

**Type**: `NotificationType::UserPromotedToAdmin`  
**Channels**: `['database']` + `['mail']` or `['slack']` (based on global setting)  
**Queue**: Yes (`ShouldQueue`)  
**Eligibility**: Checked via `NotificationPreferenceService` (Admin users only)

**Recipients**:

- The user who was promoted to admin
- Only if they have enabled `UserPromotedToAdmin` notifications

**When Sent**:

- When a user is promoted to admin role (`UserObserver::updated()`)
- Triggered when `user_type` changes to `RoleType::Admin`

**Purpose**:

- Congratulates the user on their promotion
- Informs them about new administrative capabilities
- Personal notification to the promoted user

**Special Notes**:

- **Admin-only notification** - only visible to admin users
- Sent to the promoted user (different from `AdminPromotionNotification` which goes to other admins)
- Message: "You have been promoted to administrator"

---

### User Promoted To Maintenance Notification

**Type**: `NotificationType::UserPromotedToMaintenance`  
**Channels**: `['database']` + `['mail']` or `['slack']` (based on global setting)  
**Queue**: Yes (`ShouldQueue`)  
**Eligibility**: Checked via `NotificationPreferenceService` (Maintenance users only)

**Recipients**:

- The user who was promoted to maintenance
- Only if they have enabled `UserPromotedToMaintenance` notifications

**When Sent**:

- When a user is promoted to maintenance role (`UserObserver::updated()`)
- Triggered when `user_type` changes to `RoleType::Maintenance`

**Purpose**:

- Congratulates the user on their promotion
- Informs them about maintenance features and API monitoring capabilities
- Personal notification to the promoted user

**Special Notes**:

- **Maintenance-only notification** - only visible to maintenance users
- Sent to the promoted user (different from `MaintenancePromotionNotification` which goes to admins)
- User's notification preferences are re-initialized when promoted to include Maintenance-only notifications
- Message: "You have been promoted to maintenance role"

---

### User Demoted From Admin Notification

**Type**: `NotificationType::UserDemotedFromAdmin`  
**Channels**: `['mail']` (always email only, cannot be disabled)  
**Queue**: Yes (`ShouldQueue`)  
**Eligibility**: Always sent (bypasses notification preferences)

**Recipients**:

- The user who was demoted from admin
- Always sent (mandatory notification)

**When Sent**:

- When a user is demoted from admin role (`UserObserver::updated()`)
- Triggered when `user_type` changes from `RoleType::Admin` to another role

**Purpose**:

- Informs the user that their administrator role has been removed
- Explains that they no longer have access to administrative features
- Personal notification to the demoted user

**Special Notes**:

- **Mandatory notification** - always sends via email only, bypasses all preferences
- **Admin-only notification** - only visible to admin users (in notification preferences)
- Sent to the demoted user (different from `AdminDemotionNotification` which goes to other admins)
- Message: "You have been removed from administrator role"

---

### User Demoted From Maintenance Notification

**Type**: `NotificationType::UserDemotedFromMaintenance`  
**Channels**: `['mail']` (always email only, cannot be disabled)  
**Queue**: Yes (`ShouldQueue`)  
**Eligibility**: Always sent (bypasses notification preferences)

**Recipients**:

- The user who was demoted from maintenance
- Always sent (mandatory notification)

**When Sent**:

- When a user is demoted from maintenance role (`UserObserver::updated()`)
- Triggered when `user_type` changes from `RoleType::Maintenance` to another role

**Purpose**:

- Informs the user that their maintenance role has been removed
- Explains that they no longer have access to maintenance features and system monitoring capabilities
- Personal notification to the demoted user

**Special Notes**:

- **Mandatory notification** - always sends via email only, bypasses all preferences
- **Maintenance-only notification** - only visible to maintenance users (in notification preferences)
- Sent to the demoted user (different from `MaintenanceDemotionNotification` which goes to admins)
- Message: "You have been removed from maintenance role"

---

## Admin Notifications (About Others)

### Admin Promotion Notification

**Type**: `NotificationType::AdminPromotion`  
**Channels**: `['database']` + `['mail']` or `['slack']` (based on global setting)  
**Queue**: Yes (`ShouldQueue`)  
**Eligibility**: Checked via `NotificationPreferenceService` (Admin users only)

**Recipients**:

- All other admin users (excluding the promoted user)
- Only if they have enabled `AdminPromotion` notifications

**When Sent**:

- When a user is promoted to admin role (`UserObserver::updated()`)
- Triggered when `user_type` changes to `RoleType::Admin`

**Purpose**:

- Notifies existing admins when a new admin is promoted
- Includes who was promoted and who performed the promotion (if available)
- Keeps admin team informed about role changes

**Special Notes**:

- **Admin-only notification** - only visible to admin users
- Excludes the promoted user from recipient list
- Message: "{Name} has been promoted to admin"

---

### Admin Demotion Notification

**Type**: `NotificationType::AdminDemotion`  
**Channels**: `['database']` + `['mail']` or `['slack']` (based on global setting)  
**Queue**: Yes (`ShouldQueue`)  
**Eligibility**: Checked via `NotificationPreferenceService` (Admin users only)

**Recipients**:

- All admin users
- Only if they have enabled `AdminDemotion` notifications

**When Sent**:

- When a user is demoted from admin role (`UserObserver::updated()`)
- Triggered when `user_type` changes from `RoleType::Admin` to another role

**Purpose**:

- Notifies admins when someone is demoted from admin
- Includes who was demoted and who performed the demotion (if available)
- Maintains transparency about role changes

**Special Notes**:

- **Admin-only notification** - only visible to admin users
- Message: "{Name} has been demoted from admin"

---

### Maintenance Promotion Notification

**Type**: `NotificationType::MaintenancePromotion`  
**Channels**: `['database']` + `['mail']` or `['slack']` (based on global setting)  
**Queue**: Yes (`ShouldQueue`)  
**Eligibility**: Checked via `NotificationPreferenceService` (Admin users only)

**Recipients**:

- All admin users
- Only if they have enabled `MaintenancePromotion` notifications

**When Sent**:

- When a user is promoted to maintenance role (`UserObserver::updated()`)
- Triggered when `user_type` changes to `RoleType::Maintenance`

**Purpose**:

- Notifies admins when a user is promoted to maintenance role
- Includes who was promoted and who performed the promotion (if available)
- Keeps admin team informed about maintenance role assignments

**Special Notes**:

- **Admin-only notification** - only visible to admin users
- Message: "{Name} has been promoted to maintenance"

---

### Maintenance Demotion Notification

**Type**: `NotificationType::MaintenanceDemotion`  
**Channels**: `['database']` + `['mail']` or `['slack']` (based on global setting)  
**Queue**: Yes (`ShouldQueue`)  
**Eligibility**: Checked via `NotificationPreferenceService` (Admin users only)

**Recipients**:

- All admin users
- Only if they have enabled `MaintenanceDemotion` notifications

**When Sent**:

- When a user is demoted from maintenance role (`UserObserver::updated()`)
- Triggered when `user_type` changes from `RoleType::Maintenance` to another role

**Purpose**:

- Notifies admins when a user is removed from maintenance role
- Includes who was demoted and who performed the demotion (if available)
- Maintains transparency about role changes

**Special Notes**:

- **Admin-only notification** - only visible to admin users
- Message: "{Name} has been removed from maintenance role"

---

## Authentication Notifications

### Welcome New User Notification

**Type**: `NotificationType::WelcomeNewUser`  
**Channels**: `['mail']` (always email only, cannot be disabled)  
**Queue**: Yes (`ShouldQueue`)  
**Eligibility**: Always sent (bypasses notification preferences)

**Recipients**:

- The newly created user (or user attempting to login)

**When Sent**:

- When a new user is created without a password (`UserObserver::created()`)
- When a user without a password attempts to login (`LoginController::store()`)

**Purpose**:

- Provides password setup link for new users who don't have a password yet
- Contains secure token for password creation (generated from user ID, email, and created_at timestamp)
- Required for account initialization - user cannot login without setting up password first

**Special Notes**:

- **This notification cannot be disabled globally**
- Always sent via email only (not affected by global notification channel setting)
- Only sent to users without passwords (users with passwords don't receive any welcome notification)
- Password setup link includes secure token: `route('password.setup', ['email' => $email, 'token' => $token])`

---

### Reset Password Notification

**Type**: N/A (extends Laravel's `ResetPassword`)  
**Channels**: `['mail']` (Laravel default)  
**Queue**: Yes (`ShouldQueue`)  
**Eligibility**: Always sent

**Recipients**:

- The user requesting password reset

**When Sent**:

- When a user requests a password reset (`User::sendPasswordResetNotification()`)

**Purpose**:

- Provides password reset token and link
- Allows users to reset forgotten passwords

**Special Notes**:

- Extends Laravel's built-in password reset notification
- Made asynchronous via queue implementation
- Always sent via email only (Laravel default)

---

## Notification Flow

1. **Trigger**: Event occurs (user created, role changed, API down, schedule change, etc.)
2. **Eligibility Check**: `NotificationPreferenceService` checks if user can receive notification
   - Checks global notification preferences
   - Checks user-specific notification preferences
   - Checks role-based restrictions
3. **Notification Creation**: Notification instance created with relevant data
4. **Queue**: Notification queued (if implements `ShouldQueue`) for asynchronous processing
5. **Channel Selection**: `via()` method determines delivery channels based on global setting
6. **Delivery**: Notification sent via selected channels (mail, slack, database)

---

## Role-Based Restrictions

### Admin-Only Notifications

These notifications are only visible and available to users with admin role:

- `AdminPromotionNotification`
- `AdminDemotionNotification`
- `MaintenancePromotionNotification`
- `MaintenanceDemotionNotification`
- `UserPromotedToAdminNotification`

### Maintenance-Only Notifications

These notifications are only visible and available to users with maintenance role:

- `ApiDownNotification`
- `UserPromotedToMaintenanceNotification`

### All Users

These notifications are available to all users:

- `ScheduleChange`
- `LeaveReminder`

---

## Special Behaviors

### Always Sent (Bypass Preferences)

These notifications always send regardless of user preferences:

- **WelcomeNewUserNotification**: Required for password setup
- **ResetPasswordNotification**: Required for password reset

### Channel Overrides

Some notifications have fixed channels that cannot be changed:

- **WelcomeNewUserNotification**: Always email only (password setup link must be delivered via email)
- **ResetPasswordNotification**: Always email only (password reset link must be delivered via email)

### Rate Limiting

- **ApiDownNotification**: Implements 60-minute rate limiting per service per user to prevent spam
  - Uses Laravel's native `RateLimited` queue middleware
  - Rate limiter configured in `AppServiceProvider` using `RateLimiter::for()`
  - Prevents duplicate notifications when multiple sync jobs fail simultaneously
  - Each service (SystemPin, Odoo, ProofHub, DeskTime) has separate rate limit windows
  - Rate-limited jobs are automatically released back to the queue with appropriate delays

- **UnlinkedPlatformUserNotification**: Implements 24-hour rate limiting per platform per external ID per user
  - Uses Laravel's native `RateLimited` queue middleware
  - Rate limiter configured in `AppServiceProvider` using `RateLimiter::for()`
  - Prevents notification spam for the same unlinked user across multiple sync runs

- **VerifyEmailNotification**: Implements 5-minute rate limiting per user
  - Uses Laravel's native `RateLimited` queue middleware
  - Rate limiter configured in `AppServiceProvider` using `RateLimiter::for()`
  - Prevents email verification spam

### Queuing

- **All notifications** implement `ShouldQueue` and are processed asynchronously
- This prevents blocking user creation/login processes and improves application responsiveness

---

## Related Files

- **Notifications**: `app/Notifications/`
- **Observers**: `app/Observers/UserObserver.php`, `app/Observers/UserScheduleObserver.php`
- **Jobs**: `app/Jobs/SendUserLeaveReminderJob.php`
- **Actions**: `app/Actions/*/Check*HealthAction.php`
- **Enums**: `app/Enums/NotificationType.php`
- **Models**: `app/Models/User.php`, `app/Models/UserNotificationPreference.php`, `app/Models/GlobalNotificationPreference.php`
