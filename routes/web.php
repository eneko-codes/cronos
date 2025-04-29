<?php

use App\Http\Controllers\LoginController;
use App\Livewire\Dashboard;
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
Route::middleware(['guest', 'throttle:login'])->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/login/verify', [LoginController::class, 'verify'])->name(
        'login.verify'
    );
});

// Protected routes for authenticated users
Route::middleware(['auth', 'throttle:api'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/', Dashboard::class)->name('dashboard');

    // All other routes require admin access
    Route::middleware(['can:viewAny,App\Models\User', 'throttle:admin'])->group(
        function () {
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
        }
    );
});
