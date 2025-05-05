# Cronos

Cronos is a Laravel application designed to synchronize data from various external platforms (Odoo, ProofHub, DeskTime) and provide related functionalities. It uses Livewire 3 for dynamic frontend components.

## 🚀 Requirements

- PHP 8.2+
- Composer
- Node.js & npm
- A database supported by Laravel (e.g., MySQL, PostgreSQL, SQLite)
- A queue worker (e.g., Supervisor) for background job processing

## 🛠️ Installation

1.  **Clone the repository:**

    ```bash
    git clone <repository-url>
    cd cronos
    ```

2.  **Install PHP Dependencies:**

    ```bash
    composer install
    ```

3.  **Install Node.js Dependencies:**

    ```bash
    npm install
    ```

4.  **Setup Environment:**
    Copy the example environment file and configure it for your local setup:

    ```bash
    cp .env.example .env
    ```

    Open the `.env` file and update the following sections at minimum:

    - **Database Credentials:** Configure `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`. If using SQLite (the default in `.env.example`), ensure the `DB_DATABASE` path points to your desired location (e.g., `database/database.sqlite`).
    - **Application URL:** Set `APP_URL` to the URL you'll use for local development (e.g., `http://cronos.test`).
    - **API Credentials:** Fill in the values for:
      - `ODOO_BASE_URL`
      - `ODOO_DATABASE`
      - `ODOO_USERNAME`
      - `ODOO_PASSWORD`
      - `PROOFHUB_COMPANY_URL`
      - `PROOFHUB_API_KEY`
      - `DESKTIME_BASE_URL`
      - `DESKTIME_API_KEY`

5.  **Generate Application Key:**

    ```bash
    php artisan key:generate
    ```

6.  **Run Database Migrations:**
    If using SQLite and the file doesn't exist, create it first: `touch database/database.sqlite` (adjust path if changed in `.env`).

    ```bash
    php artisan migrate
    ```

7.  **Compile Frontend Assets:**

    ```bash
    npm run build
    ```

8.  **Configure Queue Worker:**
    Set up a queue worker like Supervisor to process the `php artisan queue:work` command. This is crucial for data synchronization jobs. Refer to the [Laravel Queue Documentation](https://laravel.com/docs/12.x/queues#supervisor-configuration).

9.  **Configure Web Server:**
    Configure your web server (e.g., Nginx, Apache) to point to the `public` directory.

## ⚙️ Configuration

### API Connections

Update your `.env` file with the correct credentials and URLs for Odoo, ProofHub, and DeskTime as outlined in Installation step 4.

After changing `.env` variables, clear the configuration cache:

```bash
php artisan config:clear
php artisan cache:clear
```

### Scheduled Synchronization

Data synchronization jobs are designed to run automatically. The schedule frequency might be configurable (check `app/Console/Kernel.php` or database settings if `Kernel.php` is not standard). Ensure the Laravel scheduler is running by adding the following Cron entry to your server:

```cron
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

_(Note: The `job_frequencies` table mentioned in the old README was not verified and might not be the current method for configuring sync frequency.)_

## ▶️ Usage

### Data Synchronization Command (`sync`)

Manually trigger data synchronization using the `sync` Artisan command.

**Command Signature:**

```bash
php artisan sync {platform?} {type?} [--from=Y-m-d] [--to=Y-m-d] [--user-id=ID]
```

- `platform`: `odoo`, `proofhub`, `desktime`, or `all` (required if `type` is specified, otherwise shows help).
- `type`: Specific data type to sync (e.g., `users`, `leaves`, `projects`, `attendances`). Varies by platform.
- `--from`: Start date for date-based sync (e.g., `leaves`, `time-entries`, `attendances`).
- `--to`: End date for date-based sync.
- `--user-id`: Filter by user ID (currently only for `desktime attendances`).

**Examples:**

```bash
# Show general help
php artisan sync

# Show help for Odoo
php artisan sync odoo

# Sync all data from all platforms (dispatches a batch job)
php artisan sync all

# Sync all data for ProofHub (dispatches a batch job)
php artisan sync proofhub

# Sync only Odoo users
php artisan sync odoo users

# Sync DeskTime attendances for a specific user and date range
php artisan sync desktime attendances --user-id=123 --from=2024-01-01 --to=2024-01-31
```

Jobs are dispatched to the queue and processed by your queue worker.

### Data Retention (`app:purge-old-time-data`)

Automatically delete old time-related data based on configured retention periods (likely managed via `App\Models\Setting`).

**Command Signature:**

```bash
php artisan app:purge-old-time-data [--dry-run]
```

- `--dry-run`: Show what would be deleted without actually deleting.

This command is typically run via the scheduler.

### Queue Management

Monitor and manage the queue:

```bash
# View active jobs (using Laravel Pulse or Telescope if configured)
# Or check queue status via standard Laravel commands if Pulse/Telescope are not used
# php artisan queue:monitor (if a suitable driver is installed)

# View failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry [failed-job-id]

# Clear failed jobs
php artisan queue:flush

# Clear all jobs from a queue
php artisan queue:clear [connection-name] --queue=default
```

## 👨🏻‍💻 Development

### Running Locally

Use the `dev` script defined in `composer.json` for a convenient local development environment:

```bash
composer dev
```

This typically starts the PHP development server, a queue listener, the `pail` log viewer, and the Vite development server concurrently.

### Development Tools & Quality Checks

This project includes tools to help maintain code quality:

- **Pint:** For PHP code style formatting.
  - Check: `composer lint`
  - Fix: `composer fix`
- **Rector:** For automated PHP code refactoring (configuration in `rector.php`).
- **PHPStan:** For static analysis.
  - Run: `composer analyse`
- **Prettier:** For Blade template formatting (run via pre-commit hook or manually).
- **Pest:** For running tests.
  - Run: `composer test`
  - Run with coverage: `composer test-coverage`

Consider setting up the Git pre-commit hook (script available in the project history if needed) to automate formatting and checks before committing.

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

---

### Technical Details

- **Models:** `App\Models\Setting`, `App\Models\UserNotificationPreference`
- **Livewire Components:** `App\Livewire\Settings`, `App\Livewire\Sidebar`
- **Views:** `resources/views/livewire/settings.blade.php`, `resources/views/livewire/sidebar.blade.php`
- **Migration:** `database/migrations/2024_12_10_182573_create_user_notification_preferences_table.php` (Modified to remove `admin_promotion`)

## 🗑️ Data Retention

Cronos includes a feature to automatically delete old user time-related data to manage database size and comply with data retention policies.

### Pre-commit Hook

This project uses a Git pre-commit hook to automatically format and check code before it is committed. The hook ensures consistency and helps catch potential issues early.

**Setup (Recommended):**

Git hooks are not version controlled, so you need to set this up manually once per local clone. Follow these steps from the project root:

1.  **Create the hook file (if it doesn't exist):**

    ```bash
    touch .git/hooks/pre-commit
    ```

2.  **Open the file** (`.git/hooks/pre-commit`) in your text editor.

3.  **Paste the entire script content below** into the file, replacing any existing content:

    ```sh
    #!/bin/sh
    #
    # Pre-commit hook that runs formatters (Pint, Rector, Prettier)
    # on staged files and checks for debug statements.
    # Modifications are automatically staged.
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

      # Run Pint on the staged files. Use --quiet to reduce noise, but capture output on error.
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

    echo "Running Rector on staged PHP files..."

    # We need the list of staged PHP files again, or reuse if available
    # If Pint didn't run, STAGED_PHP_FILES would be empty, re-fetch it.
    if [ -z "$STAGED_PHP_FILES" ] && [ -z "$(git diff --cached --name-only --diff-filter=ACM -- '*.php')" ]; then
       STAGED_PHP_FILES=$(git diff --cached --name-only --diff-filter=ACM -- '*.php')
    fi

    if [ -z "$STAGED_PHP_FILES" ]; then
      echo "No staged PHP files found to Rector."
    else
      # Check if Rector is installed
      RECTOR_PATH="./vendor/bin/rector"
      if [ ! -f "$RECTOR_PATH" ]; then
          echo >&2 "Error: Rector not found at $RECTOR_PATH. Please run 'composer install'."
          exit 1
      fi

      # Run Rector process on the staged files.
      RECTOR_OUTPUT=$("$RECTOR_PATH" process $STAGED_PHP_FILES --no-progress-bar --no-diffs 2>&1)
      RECTOR_EXIT_CODE=$?

      # Check Rector exit code
      # Allow non-zero exit if it just made changes.
      if [ $RECTOR_EXIT_CODE -ne 0 ]; then
          if ! echo "$RECTOR_OUTPUT" | grep -q "Rector is done!"; then
            echo >&2 "Rector failed to process PHP files:"
            echo >&2 "$RECTOR_OUTPUT"
            exit 1
          fi
          echo "Rector applied changes."
      fi

      # Re-stage the files potentially modified by Rector
      echo "Staging potentially modified PHP files after Rector..."
      echo "$STAGED_PHP_FILES" | while IFS= read -r file; do
        # Check if the file still exists
        if [ -f "$file" ]; then
            git add "$file"
        fi
      done
      echo "Rector changes applied and staged for PHP files."
    fi

    echo "Checking Blade formatting with Prettier..."

    # Get staged Blade files
    STAGED_BLADE_FILES=$(git diff --cached --name-only --diff-filter=ACM -- '*.blade.php')

    if [ -z "$STAGED_BLADE_FILES" ]; then
      echo "No staged Blade files found to check."
    else
      # Check if node_modules exists (basic check for npm install)
      if [ ! -d "node_modules" ]; then
        echo >&2 "Error: node_modules directory not found. Please run 'npm install'."
        exit 1
      fi

      # Run Prettier --write on staged Blade files
      echo "Running Prettier --write on staged Blade files..."
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

    echo "Checking for leftover debug statements (dd, dump)..."

    # Re-fetch staged PHP files if neither Pint nor Rector block ran
    if [ -z "$STAGED_PHP_FILES" ] && [ -z "$STAGED_BLADE_FILES" ]; then
      STAGED_PHP_FILES=$(git diff --cached --name-only --diff-filter=ACM -- '*.php')
    fi

    if [ -n "$STAGED_PHP_FILES" ]; then
        # Search for dd( or dump( - ignore case, show line number, only match whole words
        FORBIDDEN_PATTERN='\b(dd|dump)\('
        DEBUG_OUTPUT=$(echo "$STAGED_PHP_FILES" | xargs grep -nwEi "$FORBIDDEN_PATTERN")

        if [ -n "$DEBUG_OUTPUT" ]; then
            echo >&2 "Error: Found forbidden debug statements in staged PHP files:"
            echo >&2 "$DEBUG_OUTPUT"
            exit 1
        fi
        echo "No leftover PHP debug statements found."
    else
        echo "No staged PHP files to check for debug statements."
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

**Checks Performed:**

When you run `git commit`, the hook will automatically perform the following actions on your _staged_ files:

1.  **PHP Formatting (Pint):** Runs `vendor/bin/pint` to format staged PHP files according to the project's coding style.
2.  **PHP Refactoring (Rector):** Runs `vendor/bin/rector process` to apply configured automated refactorings to staged PHP files.
3.  **Blade Formatting (Prettier):** Runs `npx prettier --write` to format staged `*.blade.php` files.
4.  **PHP Debug Statement Check:** Scans staged PHP files for leftover debug functions like `dd()` or `dump()` and prevents the commit if found.

Any modifications made by Pint, Rector, or Prettier will be automatically added to your commit.
