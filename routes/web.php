<?php

use App\Http\Controllers\LoginController;
use App\Http\Controllers\UserDashboardController;
use App\Livewire\LeaveTypesListView;
use App\Livewire\ProjectDetailView;
use App\Livewire\ProjectsListView;
use App\Livewire\ScheduleDetailView;
use App\Livewire\SchedulesList;
use App\Livewire\Settings;
use App\Livewire\UsersList;
use Illuminate\Support\Facades\Route;

// Public routes
// Middleware: 'guest' ensures only unauthenticated users can access these.
// Middleware: 'throttle:login' applies rate limiting to prevent brute-force attempts.
Route::middleware(['guest', 'throttle:login'])->group(function (): void {
    // Display login form
    Route::get('/login', [LoginController::class, 'create'])
        ->name('login');
    // Handle login link request
    Route::post('/login', [LoginController::class, 'store'])
        ->name('login.request');
    // Handle login link verification from email
    Route::get('/login/verify', [LoginController::class, 'verify'])
        ->middleware('signed') // Signed middleware to prevent URL tampering
        ->name('login.verify');
});

// Protected routes for authenticated users
// Middleware: 'auth' ensures only logged-in users can access these.
// Middleware: 'throttle:api' applies general API rate limiting.
Route::middleware(['auth', 'throttle:web'])->group(function (): void {
    // Route to handle user logout.
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/', UserDashboardController::class)->name('dashboard');

    // All other routes require admin access
    Route::middleware(['can:viewAny,App\Models\User', 'throttle:admin'])->group(
        function (): void {
            Route::get('/settings', Settings::class)->name('settings');
            Route::get('/users', UsersList::class)->name('users.list');
            Route::get('/user/{user}', UserDashboardController::class)->name('user.dashboard');
            Route::get('/projects', ProjectsListView::class)->name('projects.list');
            Route::get('/projects/{project:proofhub_project_id}', ProjectDetailView::class)->name('projects.show');
            Route::get('/schedules', SchedulesList::class)->name('schedules.list');
            Route::get('/schedules/{schedule:odoo_schedule_id}', ScheduleDetailView::class)->name('schedules.show');
            Route::get('/leave-types', LeaveTypesListView::class)->name('leave-types.list');
        }
    );
});
