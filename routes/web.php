<?php

declare(strict_types=1);

use App\Http\Controllers\FirstTimePasswordSetupController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\UserDashboardController;
use App\Livewire\Leave\LeaveTypesListView;
use App\Livewire\Projects\ProjectDetailView;
use App\Livewire\Projects\ProjectsListView;
use App\Livewire\Schedules\ScheduleDetailView;
use App\Livewire\Schedules\SchedulesList;
use App\Livewire\Settings\Settings;
use App\Livewire\Users\UsersList;
use Illuminate\Support\Facades\Route;

// Public routes
// Middleware: 'guest' ensures only unauthenticated users can access these.
Route::middleware(['guest'])->group(function (): void {
    // Display login form
    Route::get('/login', [LoginController::class, 'create'])
        ->name('login');

    // Handle login request - rate limited to prevent brute-force attempts
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login');

    // Forgot password routes
    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])
        ->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])
        ->name('password.email');

    // Reset password routes
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])
        ->name('password.update');

    // First-time password setup routes
    Route::get('/setup-password', [FirstTimePasswordSetupController::class, 'create'])
        ->name('password.setup');
    Route::post('/setup-password', [FirstTimePasswordSetupController::class, 'store'])
        ->name('password.setup');
});

// Protected routes for authenticated users
// Middleware: 'auth' ensures only logged-in users can access these.
// Middleware: 'throttle:web' applies general web rate limiting.
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
