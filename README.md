# Cronos

A comprehensive Laravel 12 application that synchronizes and aggregates data from multiple external platforms (Odoo, ProofHub, DeskTime, SystemPin) to provide a unified dashboard for employee time tracking, project management, and attendance monitoring.

## 🎯 Overview

Cronos serves as a central hub that synchronizes employee data, schedules, and time entries from external platforms, providing unified dashboards and reporting capabilities for comprehensive workforce management.

## ✨ Features

- **Multi-Platform Integration**: Sync data from Odoo, ProofHub, DeskTime, and SystemPin
- **Unified Dashboard**: Centralized view of all employee data and time tracking
- **Real-time Synchronization**: Automatic data updates from external platforms
- **Attendance Management**: Track both remote and office attendance
- **Project Management**: Integrated project and task tracking
- **Leave Management**: Comprehensive leave request and approval system
- **Notification System**: Customizable user and global notifications

## 🏗️ Architecture

- **Laravel 12**: Backend framework with Eloquent ORM
- **Livewire 3**: Dynamic frontend components
- **PostgreSQL**: Primary database for data storage
- **Queue System**: Background job processing for data synchronization
- **TailwindCSS**: Utility-first CSS framework for styling
- **Laravel Pulse**: Built-in application monitoring
- **Laravel Telescope**: Development debugging and monitoring

## 🚀 Quick Start

### Prerequisites

- PHP 8.2+
- Composer
- Node.js & npm
- PostgreSQL (or other Laravel-supported database)

### Installation

1. **Clone and install dependencies:**

   ```bash
   git clone <repository-url>
   cd cronos
   composer install
   npm install
   ```

2. **Environment setup:**

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Configure environment variables:**

   Edit `.env` file with your database credentials and API keys (see [Local Development Setup](docs/deployment/LOCAL_DEPLOYMENT.md) for details).

4. **Database setup:**

   ```bash
   php artisan migrate
   ```

5. **Build assets:**

   ```bash
   npm run build
   ```

6. **Configure queue worker:**
   ```bash
   php artisan queue:work
   ```

## 📚 Documentation

Comprehensive documentation is available in the [`docs/`](docs/) directory:

- **[Local Development Setup](docs/deployment/LOCAL_DEPLOYMENT.md)** - Local development environment setup
- **[Development Guide](docs/development/)** - Coding standards and best practices
- **[Database Schema](docs/database/DATABASE_SCHEMA.md)** - Database structure and relationships
- **[API Documentation](docs/api/)** - External platform integration guides
- **[Deployment Guide](docs/deployment/)** - Production deployment and monitoring

## 🔌 API Integrations

Cronos integrates with four external platforms:

- **Odoo**: Employee management, schedules, and leave tracking
- **ProofHub**: Project management and time tracking
- **DeskTime**: Remote work attendance and productivity tracking
- **SystemPin**: Office attendance from physical clocking machines

See [API Documentation](docs/api/) for detailed setup instructions.

## 🛠️ Development

### Code Quality Tools

- **Pint**: PHP code formatting (`composer fix`)
- **Rector**: Automated refactoring
- **PHPStan**: Static analysis (`composer analyse`)
- **Pest**: Testing framework (`composer test`)

## 📋 Requirements

- PHP 8.2+
- Composer
- Node.js & npm
- PostgreSQL (recommended)
- Queue worker management (see [Local Development Setup](docs/deployment/LOCAL_DEPLOYMENT.md))

## 🤝 Contributing

Please read the [Development Guide](docs/development/) for coding standards and contribution guidelines.
