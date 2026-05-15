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
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
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
        ->middleware('throttle:forgot-password')
        ->name('password.email');

    // Reset password routes
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.update');

    // First-time password setup routes
    Route::get('/setup-password/{token}', [FirstTimePasswordSetupController::class, 'create'])
        ->name('password.setup');
    Route::post('/setup-password', [FirstTimePasswordSetupController::class, 'store'])
        ->middleware('throttle:password-setup')
        ->name('password.setup.store');
});

// Protected routes for authenticated users
// Middleware: 'auth' ensures only logged-in users can access these.
// Middleware: 'throttle:web' applies general web rate limiting.
Route::middleware(['auth', 'throttle:web'])->group(function (): void {
    // Route to handle user logout.
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Laravel 12 native email verification routes
    // These routes are accessible to authenticated users regardless of verification status
    Route::get('/email/verify', function () {
        return view('auth.verify-email');
    })->name('verification.notice');

    // Handle email verification link clicks (Laravel 12 native pattern)
    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();

        return redirect()->route('dashboard')->with('toast', [
            'message' => 'Email verified successfully!',
            'variant' => 'success',
        ]);
    })->middleware(['signed', 'throttle:email-verification-resend'])->name('verification.verify');

    // Resend verification email (Laravel 12 native pattern)
    Route::post('/email/verification-notification', function (Request $request) {
        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    })->middleware('throttle:email-verification-resend')->name('verification.send');
});

// Protected routes requiring email verification
// Middleware: 'verified' ensures only users with verified emails can access these.
Route::middleware(['auth', 'verified', 'throttle:web'])->group(function (): void {
    // Dashboard (accessible to all authenticated users with verified email)
    Route::get('/', UserDashboardController::class)->name('dashboard');

    // Settings: Admin and Maintenance users (requires email verification)
    Route::middleware(['can:accessSettingsPage'])->group(function (): void {
        Route::get('/settings', Settings::class)->name('settings');
    });

    // User management routes (admin + maintenance users, requires email verification)
    Route::middleware(['can:accessUserManagement', 'throttle:admin'])->group(
        function (): void {
            Route::get('/users', UsersList::class)->name('users.list');
            Route::get('/user/{user}', UserDashboardController::class)->name('user.dashboard');
        }
    );

    // Admin-only routes (projects, schedules, leaves, requires email verification)
    Route::middleware(['can:accessAdminRoutes', 'throttle:admin'])->group(
        function (): void {
            Route::get('/projects', ProjectsListView::class)->name('projects.list');
            Route::get('/projects/{project:proofhub_project_id}', ProjectDetailView::class)->name('projects.show');
            Route::get('/schedules', SchedulesList::class)->name('schedules.list');
            Route::get('/schedules/{schedule:odoo_schedule_id}', ScheduleDetailView::class)->name('schedules.show');
            Route::get('/leave-types', LeaveTypesListView::class)->name('leave-types.list');
        }
    );
});
