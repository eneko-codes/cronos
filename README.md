## 👨🏻‍💻 SETUP SERVER

For the web app to work, you will need to install a queue manager such as Supervisor in your Ngnx instance!

## 👨🏻‍💻 Development Setup

### Git Hooks (Pre-commit)

This project uses a Git pre-commit hook to automatically format staged code before each commit, ensuring consistency. It runs:

- `Pint` on staged PHP files (`*.php`)
- `Prettier` on staged Blade files (`*.blade.php`)

Any changes made by these formatters are automatically staged. This helps prevent CI failures due to formatting issues.

**Installation (Recommended):**

To enable this hook, you need to manually create the pre-commit hook file in your local `.git/hooks` directory and make it executable. Follow these steps from the project root:

1.  **Create the hook file:**

    ```bash
    touch .git/hooks/pre-commit
    ```

2.  **Open the file** (`.git/hooks/pre-commit`) in your text editor.

3.  **Paste the entire script content below** into the file:

    ```sh
    #!/bin/sh
    #
    # Pre-commit hook that runs Pint on staged PHP files, checks Prettier for
    # Blade files, and stages any changes made by either formatter.
    #

    echo "Running Pint on staged PHP files..."

    # Get staged PHP files
    STAGED_PHP_FILES=$(git diff --cached --name-only --diff-filter=ACM -- '*.php')

    if [ -z "$STAGED_PHP_FILES" ]; then
      echo "No staged PHP files found to Pint."
    else
      # Check if Pint is installed
      PINT_PATH="./vendor/bin/pint"
      if [ ! -f "$PINT_PATH" ]; then
          echo >&2 "Error: Laravel Pint not found at $PINT_PATH. Please run 'composer install'."
          exit 1
      fi

      # Run Pint on the staged files.
      PINT_OUTPUT=$("$PINT_PATH" $STAGED_PHP_FILES 2>&1)
      PINT_EXIT_CODE=$?

      # Check Pint exit code
      if [ $PINT_EXIT_CODE -ne 0 ]; then
        echo >&2 "Pint failed to format PHP files:"
        echo >&2 "$PINT_OUTPUT"
        exit 1
      fi

      # Re-stage the files potentially modified by Pint
      echo "Staging potentially modified PHP files..."
      echo "$STAGED_PHP_FILES" | while IFS= read -r file; do
        # Check if the file still exists (it might have been deleted and staged)
        if [ -f "$file" ]; then
            git add "$file"
        fi
      done
      echo "Pint formatting applied and staged for PHP files."
    fi

    echo "Running Prettier on staged Blade files..."

    # Get staged Blade files
    STAGED_BLADE_FILES=$(git diff --cached --name-only --diff-filter=ACM -- '*.blade.php')

    if [ -z "$STAGED_BLADE_FILES" ]; then
      echo "No staged Blade files found to format."
    else
      # Check if node_modules exists (basic check for npm install)
      if [ ! -d "node_modules" ]; then
        echo >&2 "Error: node_modules directory not found. Please run 'npm install' or 'npm ci'."
        exit 1
      fi

      # Run Prettier --write on staged Blade files
      PRETTIER_OUTPUT=$(npx prettier --write $STAGED_BLADE_FILES 2>&1)
      PRETTIER_EXIT_CODE=$?

      if [ $PRETTIER_EXIT_CODE -ne 0 ]; then
        echo >&2 "Prettier formatting failed for Blade files:"
        echo >&2 "$PRETTIER_OUTPUT"
        exit 1
      fi

      # Add logic to stage modified Blade files
      echo "Staging potentially modified Blade files..."
      echo "$STAGED_BLADE_FILES" | while IFS= read -r file; do
        # Check if the file still exists
        if [ -f "$file" ]; then
            git add "$file"
        fi
      done
      echo "Prettier formatting applied and staged for Blade files."
    fi

    echo "Pre-commit checks passed."
    # Exit with 0 to allow the commit
    exit 0
    ```

4.  **Save and close** the file.

5.  **Make the hook executable:**
    ```bash
    chmod +x .git/hooks/pre-commit
    ```

**Note:** This setup needs to be performed once per local clone of the repository.

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

### Technical Details

- **Models:** `App\Models\Setting`, `App\Models\UserNotificationPreference`
- **Livewire Components:** `App\Livewire\Settings`, `App\Livewire\Sidebar`
- **Views:** `resources/views/livewire/settings.blade.php`, `resources/views/livewire/sidebar.blade.php`
- **Migration:** `database/migrations/2024_12_10_182573_create_user_notification_preferences_table.php` (Modified to remove `admin_promotion`)

## 🗑️ Data Retention

Cronos includes a feature to automatically delete old user time-related data to manage database size and comply with data retention policies.
