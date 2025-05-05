<?php

/**
 * Web Routes
 *
 * Defines the publicly accessible and authenticated routes for the application,
 * including login, magic link verification, logout, and dashboard/admin routes.
 */

use App\Http\Controllers\Auth\LoginController;
use App\Livewire\Dashboard;
use App\Livewire\LeaveTypesListView;
use App\Livewire\Login;
use App\Livewire\ProjectDetailView;
use App\Livewire\ProjectsListView;
use App\Livewire\ScheduleDetailView;
use App\Livewire\SchedulesList;
use App\Livewire\Settings;
use App\Livewire\UserDashboard;
use App\Livewire\UsersList;
use Illuminate\Support\Facades\Route;

// Public routes
// Middleware: 'guest' ensures only unauthenticated users can access these.
// Middleware: 'throttle:login' applies rate limiting to prevent brute-force attempts.
Route::middleware(['guest', 'throttle:login'])->group(function (): void {
    // Route to display the Livewire login form.
    Route::get('/login', Login::class)->name('login');
    // Route to handle the magic link verification clicked from the user's email.
    // Uses 'signed' middleware to prevent URL tampering.
    Route::get('/login/verify', [LoginController::class, 'verify'])
        ->middleware(['signed'])
        ->name('login.verify');
});

// Protected routes for authenticated users
// Middleware: 'auth' ensures only logged-in users can access these.
// Middleware: 'throttle:api' applies general API rate limiting.
Route::middleware(['auth', 'throttle:api'])->group(function (): void {
    // Route to handle user logout.
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/', Dashboard::class)->name('dashboard');

    // All other routes require admin access
    Route::middleware(['can:viewAny,App\Models\User', 'throttle:admin'])->group(
        function (): void {
            Route::get('/settings', Settings::class)->name('settings');
            Route::get('/users', UsersList::class)->name('users.list');
            Route::get('/user/{id}', UserDashboard::class)->name('user.dashboard');
            Route::get('/projects', ProjectsListView::class)->name('projects.list');
            Route::get(
                '/projects/{project:proofhub_project_id}',
                ProjectDetailView::class
            )->name('projects.show');
            Route::get('/schedules', SchedulesList::class)->name('schedules.list');
            Route::get(
                '/schedules/{schedule:odoo_schedule_id}',
                ScheduleDetailView::class
            )->name('schedules.show');
            Route::get('/leave-types', LeaveTypesListView::class)->name('leave-types.list');
        }
    );
});
