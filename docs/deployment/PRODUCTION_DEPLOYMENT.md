# Production Deployment Guide

## 🚀 Production Requirements

- **PHP 8.2+** with required extensions
- **PostgreSQL** database
- **Supervisor** for queue workers
- **Nginx** or **Apache** web server
- **SSL Certificate** for HTTPS
- **Mail service** (SMTP/SES) for notifications

## 🏠 Hosting Recommendations

**Recommended:** [Laravel Forge](https://forge.laravel.com/) for automated server management and deployment.

**Alternative:** Manual installation on a custom VPS is possible but requires advanced server administration knowledge.

## 🚀 Deployment with Laravel Forge

1. **Connect your repository** to Laravel Forge
2. **Configure environment variables** in Forge dashboard
3. **Set up queue workers** through Forge interface
4. **Configure mail settings** in your `.env` file
5. **Deploy** with one click

## 📧 Essential Environment Configuration

**Required .env settings for production:**

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=cronos_production
DB_USERNAME=cronos_user
DB_PASSWORD=secure_password

# Mail Configuration (REQUIRED)
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-server.com
MAIL_PORT=587
MAIL_USERNAME=your-email@domain.com
MAIL_PASSWORD=your-email-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@your-domain.com"
MAIL_FROM_NAME="Cronos"

# Queue
QUEUE_CONNECTION=database
```

## 🔧 Post-Deployment Setup

```bash
# Run migrations
php artisan migrate --force

# Laravel 12 optimization (individual cache commands)
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

## 📧 Mail Configuration

### SMTP Setup (Recommended)

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@your-domain.com"
MAIL_FROM_NAME="Cronos"
```

### AWS SES Setup

```env
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=us-east-1
MAIL_FROM_ADDRESS="noreply@your-domain.com"
```

## 🔄 Updates

```bash
# Deploy updates
git pull origin main
composer install --no-dev --optimize-autoloader
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan queue:restart
```
