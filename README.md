## 👨🏻‍💻 SETUP SERVER

For the web app to work, you will need to install a queue manager such as Supervisor in your Ngnx instance!

## ⛓️ SETUP API CONNECTIONS

### Odoo

#### It will return the following data in XML-RPC format.

- Employee details:
  - Name
  - Email
  - Odoo ID
- Employee calendar data (from the Calendar module):
  - Weekly work hours
  - Vacations

#### Connect Odoo to the web app:

- [x] Log into the admin account.
- [ ] On the top right part of the screen clik on "My Account".
- [ ] Click on the "Account security" tab.
- [ ] Under the "API Keys" section, generate a new key and copy it.
- [ ] Go to the Laravel project, open the .env (located in the root directory) and pass it in "ODOO_API_KEY".
- [ ] Set the "ODOO_URL" to the URL of your Odoo site (domain.odoo.com).

---

### Desktime

#### It will return the following data in JSON format:

- Employee details:
  - Name
  - Email
  - Desktime ID
- Employee remote work hours

#### Connect DeskTime to the web app:

- [x] Log into the admin account.
- [ ] On the left sidebar clik on the Settings dropdown and select "API".
- [ ] Under the "Introduction" tab, you will find "Your API Key", copy it.
- [ ] Go to the Laravel project, open the .env (located in the root directory) and pass it in "DESKTIME_API_KEY".
- [ ] Set the "DESKTIME_URL" to the URL of your DeskTime site.

---

### ProofHub

#### It will return the following data in JSON format:

- Employee details:
  - Name
  - Email
  - ProofHub ID
- Projects that the employee is participating in:
  - Projects
  - Tasks

#### Connect Proofhub to the web app:

- [x] Log in to the admin account.
- [ ] On the bottom left part of the screen, click on your account avatar, a dropdown will open. Select "API access".
- [ ] Copy the API Key.
- [ ] Go to the Laravel project, open the .env (located in the root directory) and pass it in "PROOFHUB_API_KEY".
- [ ] Set the "PROOFHUB_URL" to the URL of your DeskTime site.

---

> Once you are finished setting up all the API Connections, run these commands inside the Laravel root directory:
> `php artisan config:clear` > `php artisan cache:clear`

---

## 🔄 Data Synchronization

The application provides a unified command to synchronize data from external platforms (Odoo, ProofHub, Desktime).

### Data Types Synchronized

- **Odoo**:

  - Users
  - Departments
  - Categories
  - Leave Types
  - Schedules
  - Leaves

- **ProofHub**:

  - Users
  - Projects
  - Tasks
  - Time Entries

- **DeskTime**:
  - Users
  - Attendances

### Sync Command Usage

```bash
php artisan sync {platform} {type} [options]
```

Where:

- `platform`: The platform to sync (odoo, proofhub, desktime, all)
- `type`: Optional data type to sync within that platform
- `options`: Additional parameters like date ranges

### Examples

#### Sync All Data

```bash
# Sync all data from all platforms
php artisan sync all

# Sync all data from a specific platform
php artisan sync odoo
php artisan sync proofhub
php artisan sync desktime
```

#### Sync Specific Data Types

```bash
# Sync specific Odoo data
php artisan sync odoo users
php artisan sync odoo departments
php artisan sync odoo categories
php artisan sync odoo schedules
php artisan sync odoo leave-types
php artisan sync odoo leaves

# Sync specific ProofHub data
php artisan sync proofhub users
php artisan sync proofhub projects
php artisan sync proofhub tasks
php artisan sync proofhub time-entries

# Sync specific DeskTime data
php artisan sync desktime users
php artisan sync desktime attendances
```

#### Using Date Ranges and Other Options

```bash
# Sync with date range
php artisan sync odoo leaves --from=2023-01-01 --to=2023-12-31
php artisan sync proofhub time-entries --from=2023-01-01

# Sync with user filter
php artisan sync desktime attendances --user-id=123 --from=2023-01-01
```

### Help Information

To see available options and data types:

```bash
# Show general help
php artisan sync

# Show platform-specific help
php artisan sync odoo
php artisan sync proofhub
php artisan sync desktime
```

### Scheduled Synchronization

Cronos uses Laravel's scheduler to automatically sync data on a configurable schedule.

#### Configuration

The sync frequency is configured in the database using the `job_frequencies` table. The current setting can be viewed with:

```bash
php artisan tinker --execute="App\Models\JobFrequency::first()"
```

Available frequencies include:

- `never` - Jobs won't run
- `everyMinute` - Every minute
- `everyFiveMinutes` - Every 5 minutes
- `everyFifteenMinutes` - Every 15 minutes
- `everyThirtyMinutes` - Every 30 minutes
- `hourly` - Every hour (default)
- `daily` - Once per day at midnight

To update the frequency:

```bash
php artisan tinker --execute="App\Models\JobFrequency::first()->update(['frequency' => 'hourly'])"
```

#### How It Works

1. The Laravel scheduler (`php artisan schedule:run`) runs every minute via cron
2. It checks what jobs are scheduled to run
3. When it's time, it runs the sync command
4. This creates a batch containing all sync jobs
5. The batched jobs are processed by the queue worker

### Queue Management

#### Starting the Queue Worker

```bash
php artisan queue:work
```

#### Checking Job Status

To view active jobs:

```bash
php artisan queue:monitor
```

To view failed jobs:

```bash
php artisan queue:failed
```

### Troubleshooting

#### Common Issues

- **"No scheduled commands are ready to run"**: This means the scheduler ran successfully but no commands were due to run at that moment. This is normal.

- **"Failed to schedule sync batch"**: Check the logs in `storage/logs/laravel-*.log` for detailed error messages. Common causes include:
  - Database connection issues
  - Missing API credentials
  - API rate limits

#### Reset Procedure

If you need to start fresh:

1. Clear the queue:

```bash
php artisan queue:clear
```

2. Clear failed jobs:

```bash
php artisan queue:flush
```

3. Run a manual sync:

```bash
php artisan sync all
```

---

## 🔒 Security Features

### Enhanced Authentication Logging

The application includes detailed authentication logging for security monitoring and auditing:

#### Authentication Log Features:

- **Dedicated Log Channel**: All authentication events are logged to a separate `auth.log` file with 30-day retention
- **Detailed Event Capture**: Logs include timestamps, IP addresses, user agents, and session IDs
- **Comprehensive Coverage**: Logs all login-related events:
  - Login attempts (successful and failed)
  - Token generation
  - Token verification
  - Token expiration

#### Log Events Captured:

- Magic link login attempts
- Magic link token generation
- Token verification attempts
- Failed token validations
- Token expiration
- Successful logins
- Authentication failures

#### Log Data Fields:

- User ID and email (when available)
- IP address
- User agent
- Timestamp (ISO 8601 format)
- Session ID
- Authentication guard
- Login token information (when applicable)

#### Viewing Authentication Logs:

```bash
# View the most recent authentication logs
tail -f storage/logs/auth.log

# Search for failed login attempts
grep "failed\|warning" storage/logs/auth.log

# Search for activity from a specific IP
grep "192.168.1.1" storage/logs/auth.log

# Search for activity from a specific user
grep "user@example.com" storage/logs/auth.log
```

---
