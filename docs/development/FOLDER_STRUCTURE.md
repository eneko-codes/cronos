# 📁 Cronos Folder Structure Guide

## 🎯 **Overview**

This document outlines the organized folder structure for the Cronos Laravel application, ensuring maintainable, scalable, and easy-to-navigate code organization.

## 📋 **Current Organized Structure**

### **🏠 Livewire Components**

```
app/Livewire/
├── Dashboard/           # Dashboard & timesheet components
│   ├── UserDashboardWidgets.php
│   └── UserTimeSheetTable.php
├── Users/              # User management components
│   ├── UserDetailsModal.php
│   ├── UserProfileHeader.php
│   └── UsersList.php
├── Projects/           # Project management components
│   ├── ProjectDetailView.php
│   └── ProjectsListView.php
├── Schedules/          # Schedule management components
│   ├── ScheduleDetailView.php
│   └── SchedulesList.php
├── Leave/              # Leave management components
│   └── LeaveTypesListView.php
├── Notifications/      # Notification components
│   └── NotificationDetailsModal.php
├── Settings/           # Settings components
│   └── Settings.php
└── UI/                 # Reusable UI components
    ├── AppTime.php
    ├── LastSynced.php
    ├── Sidebar.php
    ├── SidebarToggle.php
    └── Toast.php
```

### **🎨 Blade Views**

```
resources/views/livewire/
├── dashboard/          # Dashboard views
│   ├── user-dashboard-widgets.blade.php
│   ├── user-dashboard-widgets-skeleton.blade.php
│   ├── user-time-sheet-table.blade.php
│   └── user-time-sheet-table-skeleton.blade.php
├── users/              # User management views
│   ├── user-details-modal.blade.php
│   ├── user-profile-header.blade.php
│   ├── user-profile-header-skeleton.blade.php
│   ├── users-list.blade.php
│   └── users-list-skeleton.blade.php
├── projects/           # Project management views
│   ├── project-detail-view.blade.php
│   ├── project-detail-view-skeleton.blade.php
│   ├── projects-list-view.blade.php
│   └── projects-list-view-skeleton.blade.php
├── schedules/          # Schedule management views
│   ├── schedule-detail-view.blade.php
│   ├── schedules-list.blade.php
│   └── schedules-list-skeleton.blade.php
├── leave/              # Leave management views
│   ├── leave-types-list-view.blade.php
│   └── leave-types-list-view-skeleton.blade.php
├── notifications/      # Notification views
│   └── notification-details-modal.blade.php
├── settings/           # Settings views
│   ├── settings.blade.php
│   └── settings-skeleton.blade.php
└── ui/                 # Reusable UI views
    ├── app-time.blade.php
    ├── last-synced.blade.php
    ├── sidebar.blade.php
    ├── sidebar-toggle.blade.php
    └── toast.blade.php
```

### **⚙️ Services & DTOs**

```
app/Services/Dashboard/          # Dashboard services
├── AttendanceService.php
├── DeviationCalculator.php
├── LeaveService.php
├── ScheduleService.php
└── TimeEntryService.php

app/DataTransferObjects/Dashboard/  # Dashboard DTOs
├── AttendanceData.php
├── DayData.php
├── DeviationData.php
├── LeaveData.php
├── PeriodTotals.php
├── ScheduleData.php
└── TimeEntryData.php
```

## 🎯 **Organization Principles**

### **1. Domain-Driven Design**

- **Group by business domain** (Users, Projects, Schedules, Dashboard)
- **Separate concerns** (UI components vs business logic)
- **Logical grouping** of related functionality

### **2. Laravel Conventions**

- **Follow Laravel folder structure** for standard directories
- **Use kebab-case** for Blade view files
- **Use PascalCase** for PHP class files
- **Match namespaces** to folder structure

### **3. Naming Conventions**

#### **Folder Names**

- **PascalCase** for PHP directories (`Dashboard/`, `Users/`)
- **kebab-case** for Blade directories (`dashboard/`, `users/`)

#### **File Names**

- **PascalCase** for PHP files (`UserTimeSheetTable.php`)
- **kebab-case** for Blade files (`user-time-sheet-table.blade.php`)

#### **Namespaces**

```php
// Match folder structure
namespace App\Livewire\Dashboard;
namespace App\Services\Dashboard;
namespace App\DataTransferObjects\Dashboard;
```

#### **View Paths**

```php
// Match folder structure
return view('livewire.dashboard.user-time-sheet-table');
return view('livewire.users.user-details-modal');
```

## 📋 **Folder Structure Rules**

### **When to Create New Folders**

- ✅ **5+ related components** in a domain
- ✅ **Clear business separation** (e.g., Dashboard vs Users)
- ✅ **Reusable components** that don't fit existing domains
- ✅ **Complex functionality** requiring multiple files

### **When to Keep in Root**

- ✅ **Single-purpose components** with no related files
- ✅ **Utility components** used across multiple domains
- ✅ **Core application components** (like AppTime, LastSynced)

### **Folder Organization Guidelines**

#### **Dashboard Domain**

- **Time sheet components** and related functionality
- **Period navigation** and date handling
- **Dashboard widgets** and summaries
- **Data visualization** components

#### **Users Domain**

- **User management** components
- **User profiles** and details
- **User lists** and filtering
- **User-related modals** and forms

#### **Projects Domain**

- **Project management** components
- **Project details** and views
- **Project lists** and filtering
- **Project-related functionality**

#### **Schedules Domain**

- **Schedule management** components
- **Schedule details** and views
- **Schedule lists** and filtering
- **Schedule-related functionality**

#### **Leave Domain**

- **Leave management** components
- **Leave types** and categories
- **Leave-related functionality**

#### **UI Domain**

- **Reusable UI components**
- **Common interface elements**
- **Utility components**
- **Cross-domain functionality**

## 🔄 **Maintenance Guidelines**

### **Adding New Components**

1. **Identify the domain** (Dashboard, Users, Projects, etc.)
2. **Create in appropriate folder** if domain exists
3. **Create new domain folder** if new business area
4. **Update namespace** to match folder structure
5. **Update view path** to match folder structure

### **Moving Existing Components**

1. **Update namespace** in PHP file
2. **Update view path** in render method
3. **Move Blade view** to corresponding folder
4. **Update any references** in other files
5. **Test functionality** after moving

### **Best Practices**

- ✅ **Group by business domain**
- ✅ **Use descriptive folder names**
- ✅ **Match namespaces to folders**
- ✅ **Match view paths to folders**
- ✅ **Keep related files together**
- ✅ **Use consistent naming**

### **Anti-Patterns**

- ❌ **Don't create too many small folders**
- ❌ **Don't mix domains** in the same folder
- ❌ **Don't use generic names** like "Components"
- ❌ **Don't ignore Laravel conventions**
- ❌ **Don't create deep nesting** (max 2-3 levels)

## 📚 **Examples**

### **Good Organization**

```
app/Livewire/Dashboard/UserTimeSheetTable.php
resources/views/livewire/dashboard/user-time-sheet-table.blade.php
```

### **Namespace Examples**

```php
// Good
namespace App\Livewire\Dashboard;
return view('livewire.dashboard.user-time-sheet-table');

// Bad
namespace App\Livewire;
return view('livewire.user-time-sheet-table');
```

## 🎯 **Benefits**

This organized structure provides:

- **Easy navigation** for developers
- **Clear separation** of concerns
- **Scalable organization** for growth
- **Laravel convention compliance**
- **Maintainable codebase** structure
- **Consistent patterns** across the application
- **Reduced cognitive load** when working with code
- **Better team collaboration** and onboarding

## 📖 **Related Documentation**

- **Cursor Rules**: `.cursor/rules/folder-structure-rules.mdc`
- **Laravel Conventions**: https://laravel.com/docs/12.x
- **Livewire 3 Documentation**: https://livewire.laravel.com/docs
- **Project Architecture**: `.cursor/rules/architecture-patterns.mdc`
