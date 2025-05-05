<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Services\DesktimeApiService;
use App\Services\OdooApiService;
use App\Services\ProofhubApiService;
use App\Services\SystemPinApiService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register OdooApiService as a singleton with constructor injection
        $this->app->singleton(OdooApiService::class, function ($app) {
            /** @var ConfigRepository $config */
            $config = $app['config'];
            $odooConfig = $config->get('services.odoo');

            if (! isset($odooConfig['base_url'], $odooConfig['database'], $odooConfig['username'], $odooConfig['password'])) {
                throw new InvalidArgumentException('Odoo service configuration is missing or incomplete.');
            }

            return new OdooApiService(
                baseUrl: $odooConfig['base_url'],
                database: $odooConfig['database'],
                username: $odooConfig['username'],
                password: $odooConfig['password']
            );
        });

        // Register ProofhubApiService as a singleton with constructor injection
        $this->app->singleton(ProofhubApiService::class, function ($app) {
            /** @var ConfigRepository $config */
            $config = $app['config'];
            $proofhubConfig = $config->get('services.proofhub');

            if (! isset($proofhubConfig['company_url'], $proofhubConfig['api_key'])) {
                throw new InvalidArgumentException('ProofHub service configuration is missing or incomplete.');
            }

            return new ProofhubApiService(
                companyUrl: $proofhubConfig['company_url'],
                apiKey: $proofhubConfig['api_key']
            );
        });

        // Register DesktimeApiService as a singleton with constructor injection
        $this->app->singleton(DesktimeApiService::class, function ($app) {
            /** @var ConfigRepository $config */
            $config = $app['config'];
            $desktimeConfig = $config->get('services.desktime');

            if (! isset($desktimeConfig['base_url'], $desktimeConfig['api_key'])) {
                throw new InvalidArgumentException('DeskTime service configuration is missing or incomplete.');
            }

            return new DesktimeApiService(
                baseUrl: $desktimeConfig['base_url'],
                apiKey: $desktimeConfig['api_key']
            );
        });

        // Register SystemPinApiService as a singleton with constructor injection
        $this->app->singleton(SystemPinApiService::class, function ($app) {
            /** @var ConfigRepository $config */
            $config = $app['config'];
            // Get config values, allowing them to be null
            $url = $config->get('services.systempin.url');
            $key = $config->get('services.systempin.key');

            return new SystemPinApiService(
                baseUrl: $url, // Pass potentially null values
                apiKey: $key   // The service constructor handles nulls
            );
        });

        // Register TelescopeServiceProvider if the environment is local
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Only users who are admin can access pulse in production
        Gate::define('viewPulse', function (User $user) {
            return $user->isAdmin();
        });
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
