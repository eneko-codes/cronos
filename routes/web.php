<?php

use App\Http\Controllers\LoginController;
use App\Livewire\Dashboard;
use App\Livewire\Login;
use App\Livewire\Settings;
use App\Livewire\UserPage;
use App\Livewire\UsersTable;
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
      Route::get('/users', UsersTable::class)->name('users.table');
      Route::get('/user/{id}', UserPage::class)->name('user.page');
    }
  );
});
