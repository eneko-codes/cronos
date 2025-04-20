<?php

namespace App\Providers;

use App\Services\DesktimeApiCalls;
use App\Services\OdooApiCalls;
use App\Services\ProofhubApiCalls;
use App\Services\SystemPinApiCalls;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
  /**
   * Register any application services.
   */
  public function register(): void
  {
    // Register OdooApiCalls as a singleton
    $this->app->singleton(OdooApiCalls::class, function () {
      return new OdooApiCalls();
    });

    // Register ProofhubApiCalls as a singleton
    $this->app->singleton(ProofhubApiCalls::class, function () {
      return new ProofhubApiCalls();
    });

    // Register DesktimeApiCalls as a singleton
    $this->app->singleton(DesktimeApiCalls::class, function () {
      return new DesktimeApiCalls();
    });

    // Register SystemPinApiCalls as a singleton
    $this->app->singleton(SystemPinApiCalls::class, function () {
      return new SystemPinApiCalls();
    });
  }

  /**
   * Bootstrap any application services.
   */
  public function boot(): void
  {
    // Login attempts limiter
    RateLimiter::for('login', function (Request $request) {
      return Limit::perMinute(10)->by($request->ip());
    });

    // API calls limiter
    RateLimiter::for('api', function (Request $request) {
      return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
    });

    // Admin routes limiter
    RateLimiter::for('admin', function (Request $request) {
      return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
    });
  }
}
