# Local Deployment Setup

## 🚀 Requirements

- PHP 8.2+
- Composer
- Node.js & npm
- A database supported by Laravel (e.g., MySQL, PostgreSQL, SQLite)
- Queue worker management:
  - **Development**: Laravel Herd Pro (recommended) or manual `php artisan queue:work`
  - **Production**: Supervisor process manager (official Laravel recommendation)

## 🛠️ Installation Steps

### 1. Clone the Repository

```bash
git clone <repository-url>
cd cronos
```

### 2. Install PHP Dependencies

```bash
composer install
```

### 3. Install Node.js Dependencies

```bash
npm install
```

### 4. Database Setup

Choose your preferred database option:

#### Option A: PostgreSQL (Recommended for Production)

**Install PostgreSQL:**

```bash
# Ubuntu/Debian
sudo apt update
sudo apt install postgresql postgresql-contrib

# macOS (with Homebrew)
brew install postgresql
brew services start postgresql

# Windows
# Download and install from https://www.postgresql.org/download/windows/
```

**Create Database and User:**

```bash
# Switch to postgres user
sudo -u postgres psql

# Create database and user
CREATE DATABASE cronos;
CREATE USER cronos_user WITH PASSWORD 'your_secure_password';
GRANT ALL PRIVILEGES ON DATABASE cronos TO cronos_user;
\q
```

#### Option B: SQLite (Development Only)

**For development environments, SQLite is simpler:**

```bash
# Create SQLite database file
touch database/database.sqlite
```

### 5. Environment Configuration

Copy the example environment file and configure it for your local setup:

```bash
cp .env.example .env
```

Open the `.env` file and update the following sections at minimum:

**Configure Database Settings:**

**For PostgreSQL:**

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=cronos
DB_USERNAME=cronos_user
DB_PASSWORD=your_secure_password
```

**For SQLite:**

```env
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database.sqlite
```

**Configure Application Settings:**

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

### 6. Generate Application Key

```bash
php artisan key:generate
```

### 7. Run Database Migrations

```bash
php artisan migrate
```

### 8. Build Frontend Assets

```bash
npm run build
```

### 9. Configure Queue Worker

See the detailed [Queue Worker Management](#-queue-worker-management) section below for official setup instructions.

### 10. Configure Web Server

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

Data synchronization jobs are designed to run automatically. The schedule frequency is configurable in settings page. Ensure the Laravel scheduler is running by adding the following Cron entry to your server:

```cron
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

For local development run:

```bash
php artisan schedule:work
```

## 🔄 Queue Worker Management

Cronos relies heavily on background job processing for data synchronization. Proper queue worker setup is crucial for the application to function correctly. Follow the **official Laravel recommendations** based on your environment.

### 🏠 Development Environment

#### Laravel Herd Users (Recommended)

**Option 1: Laravel Herd Pro (Official)**

- **Upgrade to [Laravel Herd Pro](https://herd.laravel.com/)** for built-in service management
- Navigate to **"Services"** in Herd Pro
- Enable and configure queue workers through the GUI
- This is the **official Herd approach** for development

**Option 2: Manual Development Command**
If using free Laravel Herd, run manually during development:

```bash
php artisan queue:work --max-time=3600 --max-jobs=1000 --sleep=3 --tries=3
```

#### Other Development Environments

```bash
# Basic command for development
php artisan queue:work

# With recommended options for stability
php artisan queue:work --max-time=3600 --max-jobs=1000 --sleep=3 --tries=3
```

### 🚀 Production Environment

#### Official Laravel Recommendation: Supervisor

According to [Laravel's official documentation](https://laravel.com/docs/12.x/queues#supervisor-configuration), **Supervisor** is the recommended process manager for production queue workers.

**1. Install Supervisor:**

```bash
# Ubuntu/Debian
sudo apt-get install supervisor
```

**2. Create Configuration File:**
Create `/etc/supervisor/conf.d/cronos-worker.conf`:

```ini
[program:cronos-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/cronos/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=3
redirect_stderr=true
stdout_logfile=/path/to/cronos/storage/logs/worker.log
stopwaitsecs=3600
```

**3. Start Supervisor:**

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start cronos-worker:*
```

#### Laravel Forge (Managed Hosting)

If using [Laravel Forge](https://forge.laravel.com/):

- Navigate to your site's **"Queues"** section
- Create a new queue worker through the GUI
- Forge automatically configures Supervisor for you

### 🔄 Deployment Best Practices

When deploying application updates:

```bash
# Gracefully restart all queue workers
php artisan queue:restart
```

This command:

- Instructs workers to finish current jobs
- Automatically restarts with new code
- Requires a persistent cache driver (not `array`)

### 📊 Monitoring Queue Workers

**Check queue status:**

```bash
# View failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear all jobs (be careful!)
php artisan queue:clear
```

### 🔧 Configuration Options

Key queue configuration in `config/queue.php`:

- **Connection**: `database` (default for Cronos)
- **Max Attempts**: Configured per job class
- **Timeout**: Prevents hung processes
- **Sleep**: Pause between queue checks (3 seconds recommended)

For detailed configuration options, refer to the [Laravel Queue Documentation](https://laravel.com/docs/12.x/queues).
