# Database Schema Documentation

## 🗄️ Database Overview

Cronos uses PostgreSQL as the primary database, storing synchronized data from multiple external platforms. The schema is designed to maintain relationships between users, projects, time entries, and attendance records while supporting Laravel's authentication, session management, and notification systems.

## 📧 Email Architecture

This application uses a **dual-email storage strategy** to support both Laravel authentication and multi-platform data synchronization:

### Primary Authentication Email (`users.email`)

- **Purpose:** Laravel authentication, email verification, account setup/recovery
- **Source:** Synced from Odoo's `work_email` field (Odoo is the source of truth)
- **Usage:** Login (`Auth::attempt()`), password reset, welcome emails, email verification
- **Required:** Yes - Laravel 12 requires this field for authentication
- **Indexed:** Yes - unique index for fast authentication lookups

### Platform-Specific Emails (`user_external_identities.external_email`)

- **Purpose:** Store emails from each external platform (Odoo, DeskTime, ProofHub, SystemPin)
- **Source:** Synced from respective platform APIs
- **Usage:** Data synchronization, cross-platform user matching, notification preferences
- **Required:** No - platforms may have different emails or no email
- **Multiple:** Yes - one email per platform per user

### Why Both?

1. **Laravel 12 Requirements:** `Auth::attempt(['email' => $email, 'password' => $password])` requires `users.email`
2. **Email Verification:** Handled via `user_external_identities.email_verified_at` - the primary email is verified through the Odoo identity when user completes password setup
3. **Account Setup:** Welcome emails and password reset need a reliable, always-present email
4. **Platform Flexibility:** Users may have different emails across platforms (e.g., `user@company.com` in Odoo, `user@desktime.com` in DeskTime)
5. **Performance:** Direct column access is faster than joins for authentication queries

### Data Flow

- **Odoo sync** → `users.email` (from `work_email`) + `user_external_identities.external_email` (Odoo)
- **DeskTime sync** → `user_external_identities.external_email` (DeskTime)
- **ProofHub sync** → `user_external_identities.external_email` (ProofHub)
- **SystemPin sync** → `user_external_identities.external_email` (SystemPin)

### Notification Email Selection

- Users can select any verified platform email for notifications
- Falls back to `users.email` if no platform email is selected/verified
- Account setup/recovery notifications always use `users.email` (most reliable)

See `app/Models/User.php` and `app/Actions/Odoo/ProcessOdooUserAction.php` for implementation details.

## 📊 Core Tables

### Users Table (`users`)

Central table linking all external platform IDs and user information.

**Primary Key:** `id` (BIGSERIAL)

**Columns:**

- `id` - Primary key (auto-increment)
- `name` - User's full name (VARCHAR(255), indexed)
- `email` - **Primary authentication email** (VARCHAR(255), unique, indexed)
  - **Purpose:** Laravel authentication, account setup/recovery
  - **Source:** Synced from Odoo's `work_email` field (Odoo is the source of truth)
  - **Usage:** Login (`Auth::attempt()`), password reset, welcome emails
  - **Required:** Yes - Laravel 12 requires this field for authentication
  - **Note:** This email is also stored in `user_external_identities.external_email` for platform-specific records. Email verification is tracked via `user_external_identities.email_verified_at` (not on the users table).
- `password` - Hashed password (VARCHAR(255), nullable)
- `odoo_id` - Odoo employee ID (BIGINT, unique)
- `proofhub_id` - ProofHub user ID (BIGINT, unique)
- `desktime_id` - DeskTime user ID (BIGINT, unique)
- `systempin_id` - SystemPin user ID (VARCHAR(255), unique)
- `department_id` - Foreign key to departments table (BIGINT, nullable)
- `timezone` - User's timezone (VARCHAR(255), nullable)
- `user_type` - User role type (VARCHAR(255), default: 'user')
- `do_not_track` - Privacy flag (BOOLEAN, default: false)
- `muted_notifications` - Notification preference (BOOLEAN, default: false)
- `is_active` - Active status (BOOLEAN, default: true)
- `job_title` - Job title (VARCHAR(255), nullable)
- `odoo_manager_id` - Manager's Odoo ID (BIGINT, nullable)
- `remember_token` - Laravel remember token (VARCHAR(100), nullable)
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp

**Foreign Keys:**

- `department_id` → `departments.odoo_department_id` (ON DELETE SET NULL)

**Relationships:**

- **One-to-Many:** TimeEntries, UserAttendances, UserLeaves, UserSchedules, Sessions, UserNotificationPreferences
- **Many-to-One:** Department
- **Many-to-Many:** Projects (via project_user), Tasks (via task_user), Categories (via category_user)

### Projects Table (`projects`)

ProofHub project data with external references.

**Primary Key:** `proofhub_project_id` (BIGINT)

**Columns:**

- `proofhub_project_id` - ProofHub project ID (BIGINT, primary)
- `title` - Project title (VARCHAR(255))
- `status` - Project status (JSON, nullable)
- `description` - Project description (TEXT, nullable)
- `proofhub_created_at` - Creation time in ProofHub (TIMESTAMP, nullable)
- `proofhub_updated_at` - Last update time in ProofHub (TIMESTAMP, nullable)
- `proofhub_creator_id` - ProofHub creator ID (BIGINT, nullable)
- `proofhub_manager_id` - ProofHub manager ID (BIGINT, nullable)
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp

**Relationships:**

- **One-to-Many:** Tasks, TimeEntries
- **Many-to-Many:** Users (via project_user)

### Tasks Table (`tasks`)

Task assignments and details from ProofHub.

**Primary Key:** `proofhub_task_id` (BIGINT)

**Columns:**

- `proofhub_task_id` - ProofHub task ID (BIGINT, primary)
- `proofhub_project_id` - Foreign key to projects (BIGINT)
- `title` - Task title (VARCHAR(255))
- `status` - Task status (VARCHAR(255), nullable)
- `due_date` - Task due date (DATE, nullable)
- `description` - Task description (TEXT, nullable)
- `tags` - Task tags (JSON, nullable)
- `proofhub_creator_id` - ProofHub creator ID (BIGINT, nullable)
- `proofhub_created_at` - Creation time in ProofHub (TIMESTAMP, nullable)
- `proofhub_updated_at` - Last update time in ProofHub (TIMESTAMP, nullable)
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp

**Foreign Keys:**

- `proofhub_project_id` → `projects.proofhub_project_id`

**Relationships:**

- **Many-to-One:** Project
- **Many-to-Many:** Users (via task_user)
- **One-to-Many:** TimeEntries

### Time Entries Table (`time_entries`)

Detailed time tracking records from ProofHub.

**Primary Key:** `proofhub_time_entry_id` (BIGINT)

**Columns:**

- `proofhub_time_entry_id` - ProofHub time entry ID (BIGINT, primary)
- `user_id` - Foreign key to users (BIGINT)
- `proofhub_project_id` - Foreign key to projects (BIGINT)
- `proofhub_task_id` - Foreign key to tasks (BIGINT, nullable)
- `status` - Time entry status (VARCHAR(255), default: 'none')
- `description` - Work description (TEXT, nullable)
- `date` - Work date (DATE)
- `duration_seconds` - Duration in seconds (INTEGER, default: 0)
- `proofhub_created_at` - Creation time in ProofHub (TIMESTAMP, nullable)
- `proofhub_updated_at` - Last update time in ProofHub (TIMESTAMP, nullable)
- `billable` - Billable flag (BOOLEAN, nullable)
- `comments` - Additional comments (TEXT, nullable)
- `tags` - Time entry tags (JSON, nullable)
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp

**Foreign Keys:**

- `user_id` → `users.id`
- `proofhub_project_id` → `projects.proofhub_project_id`

**Relationships:**

- **Many-to-One:** User, Project, Task

### Departments Table (`departments`)

Organizational structure from Odoo.

**Primary Key:** `odoo_department_id` (BIGINT)

**Columns:**

- `odoo_department_id` - Odoo department ID (BIGINT, primary)
- `name` - Department name (VARCHAR(255))
- `active` - Active status (BOOLEAN, default: true)
- `odoo_manager_id` - Manager's Odoo ID (BIGINT, nullable)
- `odoo_parent_department_id` - Parent department ID (BIGINT, nullable)
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp

**Relationships:**

- **One-to-Many:** Users, UserLeaves
- **Self-Reference:** Parent departments

### Categories Table (`categories`)

User categories/tags from Odoo.

**Primary Key:** `odoo_category_id` (BIGINT)

**Columns:**

- `odoo_category_id` - Odoo category ID (BIGINT, primary)
- `name` - Category name (VARCHAR(255))
- `active` - Active status (BOOLEAN, default: true)
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp

**Relationships:**

- **Many-to-Many:** Users (via category_user)
- **One-to-Many:** UserLeaves

### Leave Types Table (`leave_types`)

Leave type definitions from Odoo.

**Primary Key:** `odoo_leave_type_id` (BIGINT)

**Columns:**

- `odoo_leave_type_id` - Odoo leave type ID (BIGINT, primary)
- `name` - Leave type name (VARCHAR(255))
- `request_unit` - Request unit (VARCHAR(255), nullable)
- `active` - Active status (BOOLEAN, default: true)
- `is_unpaid` - Unpaid leave flag (BOOLEAN, default: false)
- `requires_allocation` - Allocation requirement (BOOLEAN, default: false)
- `validation_type` - Validation type (VARCHAR(255), nullable)
- `limit` - Has limit flag (BOOLEAN, default: false)
- `odoo_created_at` - Creation time in Odoo (VARCHAR(255), nullable)
- `odoo_updated_at` - Last update time in Odoo (VARCHAR(255), nullable)
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp

**Relationships:**

- **One-to-Many:** UserLeaves

### Schedules Table (`schedules`)

Work schedule data from Odoo.

**Primary Key:** `odoo_schedule_id` (BIGINT)

**Columns:**

- `odoo_schedule_id` - Odoo schedule ID (BIGINT, primary)
- `description` - Schedule description (VARCHAR(255), nullable)
- `average_hours_day` - Average hours per day (FLOAT, nullable)
- `two_weeks_calendar` - Bi-weekly calendar flag (BOOLEAN, default: false)
- `two_weeks_explanation` - Bi-weekly explanation (VARCHAR(255), nullable)
- `flexible_hours` - Flexible hours flag (BOOLEAN, default: false)
- `active` - Active status (BOOLEAN, default: true)
- `odoo_created_at` - Creation time in Odoo (TIMESTAMP, nullable)
- `odoo_updated_at` - Last update time in Odoo (TIMESTAMP, nullable)
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp

**Relationships:**

- **One-to-Many:** ScheduleDetails, UserSchedules

### Schedule Details Table (`schedule_details`)

Detailed schedule information from Odoo.

**Primary Key:** `id` (BIGSERIAL)

**Columns:**

- `id` - Primary key (auto-increment)
- `odoo_schedule_id` - Foreign key to schedules (BIGINT)
- `odoo_detail_id` - Odoo detail ID (BIGINT)
- `name` - Detail name (VARCHAR(255), nullable)
- `weekday` - Day of week (TINYINT, 0=Sunday, 6=Saturday)
- `day_period` - Period of day (ENUM: 'morning', 'afternoon', nullable)
- `week_type` - Week type (INTEGER, default: 0)
- `date_from` - Start date (DATE, nullable)
- `date_to` - End date (DATE, nullable)
- `start` - Start time (TIME)
- `end` - End time (TIME)
- `active` - Active status (BOOLEAN, nullable)
- `odoo_created_at` - Creation time in Odoo (TIMESTAMP, nullable)
- `odoo_updated_at` - Last update time in Odoo (TIMESTAMP, nullable)
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp

**Foreign Keys:**

- `odoo_schedule_id` → `schedules.odoo_schedule_id`

**Unique Constraints:**

- `(odoo_schedule_id, odoo_detail_id)`

**Relationships:**

- **Many-to-One:** Schedule

### User Schedules Table (`user_schedules`)

User-schedule assignments with historical tracking.

**Primary Key:** `id` (BIGSERIAL)

**Columns:**

- `id` - Primary key (auto-increment)
- `user_id` - Foreign key to users (BIGINT)
- `odoo_schedule_id` - Foreign key to schedules (BIGINT)
- `effective_from` - Assignment start time (TIMESTAMP)
- `effective_until` - Assignment end time (TIMESTAMP, nullable)
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp

**Foreign Keys:**

- `user_id` → `users.id`
- `odoo_schedule_id` → `schedules.odoo_schedule_id`

**Unique Constraints:**

- `(user_id, odoo_schedule_id, effective_from)`

**Relationships:**

- **Many-to-One:** User, Schedule

### User Attendances Table (`user_attendances`)

Attendance records from DeskTime and SystemPin.

**Primary Key:** `id` (BIGSERIAL)

**Columns:**

- `id` - Primary key (auto-increment)
- `user_id` - Foreign key to users (BIGINT)
- `date` - Attendance date (DATE)
- `clock_in` - Clock in time (TIMESTAMP, nullable)
- `clock_out` - Clock out time (TIMESTAMP, nullable)
- `duration_seconds` - Duration in seconds (INTEGER, default: 0)
- `is_remote` - Remote work flag (BOOLEAN)
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp

**Foreign Keys:**

- `user_id` → `users.id`

**Relationships:**

- **Many-to-One:** User

### User Leaves Table (`user_leaves`)

Leave management data from Odoo.

**Primary Key:** `id` (BIGSERIAL)

**Columns:**

- `id` - Primary key (auto-increment)
- `odoo_leave_id` - Odoo leave ID (BIGINT, unique)
- `user_id` - Foreign key to users (BIGINT, nullable)
- `start_date` - Leave start date (DATETIME)
- `end_date` - Leave end date (DATETIME)
- `type` - Leave type (ENUM: 'employee', 'department', 'category')
- `status` - Leave status (VARCHAR(255))
- `duration_days` - Duration in days (FLOAT, nullable)
- `department_id` - Foreign key to departments (BIGINT, nullable)
- `category_id` - Foreign key to categories (BIGINT, nullable)
- `leave_type_id` - Foreign key to leave types (BIGINT, nullable)
- `request_hour_from` - Request start hour (FLOAT, nullable)
- `request_hour_to` - Request end hour (FLOAT, nullable)
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp

**Foreign Keys:**

- `user_id` → `users.id` (ON DELETE SET NULL)
- `department_id` → `departments.odoo_department_id`
- `category_id` → `categories.odoo_category_id`
- `leave_type_id` → `leave_types.odoo_leave_type_id`

**Relationships:**

- **Many-to-One:** User, Department, Category, LeaveType

## 🔐 Authentication & Session Tables

### Sessions Table (`sessions`)

Laravel session management for user authentication.

**Primary Key:** `id` (VARCHAR(255))

**Columns:**

- `id` - Session ID (VARCHAR(255), primary)
- `user_id` - Foreign key to users (BIGINT, nullable)
- `ip_address` - Client IP address (VARCHAR(45), nullable)
- `user_agent` - Client user agent (TEXT, nullable)
- `payload` - Session data (LONGTEXT)
- `last_activity` - Last activity timestamp (INTEGER, indexed)

**Foreign Keys:**

- `user_id` → `users.id` (ON DELETE SET NULL)

**Relationships:**

- **Many-to-One:** User

### Password Reset Tokens Table (`password_reset_tokens`)

Laravel's password reset token management for forgot password functionality.

**Primary Key:** `email` (VARCHAR(255))

**Columns:**

- `email` - User email address (VARCHAR(255), primary)
- `token` - Password reset token (VARCHAR(255))
- `created_at` - Token creation timestamp (TIMESTAMP, nullable)

**Relationships:**

- **One-to-One:** User (via email)

## 🔔 Notification Tables

### Notifications Table (`notifications`)

Laravel notification system for user alerts.

**Primary Key:** `id` (UUID)

**Columns:**

- `id` - Notification ID (UUID, primary)
- `type` - Notification type (VARCHAR(255))
- `notifiable_type` - Notifiable model type (VARCHAR(255))
- `notifiable_id` - Notifiable model ID (BIGINT)
- `data` - Notification data (TEXT)
- `read_at` - Read timestamp (TIMESTAMP, nullable)
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp

**Relationships:**

- **Polymorphic:** Notifiable (User)

### User Notification Preferences Table (`user_notification_preferences`)

Individual user notification settings.

**Primary Key:** `id` (BIGSERIAL)

**Columns:**

- `id` - Primary key (auto-increment)
- `user_id` - Foreign key to users (BIGINT)
- `notification_type` - Notification type (VARCHAR(255))
- `enabled` - Preference enabled (BOOLEAN, default: true)
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp

**Foreign Keys:**

- `user_id` → `users.id` (ON DELETE CASCADE)

**Unique Constraints:**

- `(user_id, notification_type)`

**Relationships:**

- **Many-to-One:** User

### Global Notification Preferences Table (`global_notification_preferences`)

System-wide notification settings.

**Primary Key:** `notification_type` (VARCHAR(255))

**Columns:**

- `notification_type` - Notification type (VARCHAR(255), primary)
- `enabled` - Global preference (BOOLEAN, default: true)
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp

## ⚙️ System Tables

### Settings Table (`settings`)

Application configuration key-value store.

**Primary Key:** `id` (BIGSERIAL)

**Columns:**

- `id` - Primary key (auto-increment)
- `key` - Setting key (VARCHAR(255), unique)
- `value` - Setting value (TEXT, nullable)
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp

## 🔗 Pivot Tables

### Project User Table (`project_user`)

Many-to-many relationship between users and projects.

**Primary Key:** `id` (BIGSERIAL)

**Columns:**

- `id` - Primary key (auto-increment)
- `user_id` - Foreign key to users (BIGINT)
- `proofhub_project_id` - Foreign key to projects (BIGINT)
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp

**Foreign Keys:**

- `user_id` → `users.id`
- `proofhub_project_id` → `projects.proofhub_project_id`

**Unique Constraints:**

- `(user_id, proofhub_project_id)`

### Task User Table (`task_user`)

Many-to-many relationship between users and tasks.

**Primary Key:** `id` (BIGSERIAL)

**Columns:**

- `id` - Primary key (auto-increment)
- `user_id` - Foreign key to users (BIGINT)
- `proofhub_task_id` - Foreign key to tasks (BIGINT)
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp

**Foreign Keys:**

- `user_id` → `users.id`
- `proofhub_task_id` → `tasks.proofhub_task_id`

**Unique Constraints:**

- `(user_id, proofhub_task_id)`

### Category User Table (`category_user`)

Many-to-many relationship between users and categories.

**Primary Key:** `(user_id, category_id)`

**Columns:**

- `user_id` - Foreign key to users (BIGINT)
- `category_id` - Foreign key to categories (BIGINT)
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp

**Foreign Keys:**

- `user_id` → `users.id`
- `category_id` → `categories.odoo_category_id`

## 📈 Indexes

### Primary Indexes

- `users.name` - Name search optimization
- `users.email` - Email lookup optimization
- `user_attendances.user_id` - User attendance queries
- `user_attendances.user_id, date` - Date-based attendance queries
- `user_attendances.user_id, date, clock_in` - Detailed attendance queries
- `user_attendances.clock_in, clock_out` - Time range queries
- `user_schedules.effective_from, effective_until` - Schedule period queries
- `schedule_details.odoo_schedule_id, weekday` - Schedule detail queries
- `sessions.last_activity` - Session cleanup optimization

## 🔗 Entity Relationship Diagram

```
Users (1) ←→ (N) Time Entries
Users (1) ←→ (N) User Attendances
Users (1) ←→ (N) User Leaves
Users (1) ←→ (N) User Schedules
Users (1) ←→ (N) Sessions
Users (1) ←→ (N) User Notification Preferences
Users (N) ←→ (1) Departments
Users (N) ←→ (N) Projects (via project_user)
Users (N) ←→ (N) Tasks (via task_user)
Users (N) ←→ (N) Categories (via category_user)

Projects (1) ←→ (N) Time Entries
Projects (1) ←→ (N) Tasks
Projects (N) ←→ (N) Users (via project_user)

Departments (1) ←→ (N) Users
Departments (1) ←→ (N) Departments (self-reference)
Departments (1) ←→ (N) User Leaves

Categories (N) ←→ (N) Users (via category_user)
Categories (1) ←→ (N) User Leaves

Leave Types (1) ←→ (N) User Leaves

Schedules (1) ←→ (N) User Schedules
Schedules (1) ←→ (N) Schedule Details
```

## 🔧 Laravel System Tables

The application includes standard Laravel system tables:

- `jobs` - Queue job management
- `failed_jobs` - Failed job tracking
- `job_batches` - Batch job management
- `cache` - Application cache
- `cache_locks` - Cache lock management
- `pulse_*` - Laravel Pulse monitoring tables
- `telescope_*` - Laravel Telescope debugging tables

These tables support Laravel's built-in functionality for queues, caching, monitoring, and debugging.
