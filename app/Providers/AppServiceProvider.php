<?php

declare(strict_types=1);

namespace App\Providers;

use App\Clients\DesktimeApiClient;
use App\Clients\OdooApiClient;
use App\Clients\ProofhubApiClient;
use App\Clients\SystemPinApiClient;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register OdooApiClient
        $this->app->singleton(OdooApiClient::class, function ($app) {
            /** @var ConfigRepository $config */
            $config = $app['config'];
            $odooConfig = $config->get('services.odoo');

            if (! isset($odooConfig['base_url'], $odooConfig['database'], $odooConfig['username'], $odooConfig['password'])) {
                throw new InvalidArgumentException('Odoo service configuration is missing or incomplete.');
            }

            return new OdooApiClient(
                baseUrl: $odooConfig['base_url'],
                database: $odooConfig['database'],
                username: $odooConfig['username'],
                password: $odooConfig['password']
            );
        });

        // Register ProofhubApiClient
        $this->app->singleton(ProofhubApiClient::class, function ($app) {
            /** @var ConfigRepository $config */
            $config = $app['config'];
            $proofhubConfig = $config->get('services.proofhub');

            if (! isset($proofhubConfig['company_url'], $proofhubConfig['api_key'])) {
                throw new InvalidArgumentException('ProofHub service configuration is missing or incomplete. Requires company_url and api_key.');
            }

            return new ProofhubApiClient(
                companyUrl: $proofhubConfig['company_url'],
                apiKey: $proofhubConfig['api_key']
            );
        });

        // Register DesktimeApiClient
        $this->app->singleton(DesktimeApiClient::class, function ($app) {
            /** @var ConfigRepository $config */
            $config = $app['config'];
            $desktimeConfig = $config->get('services.desktime');

            if (! isset($desktimeConfig['base_url'], $desktimeConfig['api_key'])) {
                throw new InvalidArgumentException('DeskTime service configuration is missing or incomplete.');
            }

            return new DesktimeApiClient(
                baseUrl: $desktimeConfig['base_url'],
                apiKey: $desktimeConfig['api_key']
            );
        });

        // Register SystemPinApiClient
        $this->app->singleton(SystemPinApiClient::class, function ($app) {
            /** @var ConfigRepository $config */
            $config = $app['config'];
            // Get config values, allowing them to be null as per original logic
            $url = $config->get('services.systempin.url');
            $key = $config->get('services.systempin.key');

            return new SystemPinApiClient(
                baseUrl: $url,
                apiKey: $key
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
        // Force HTTPS in production
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Prohibit destructive commands in production
        if ($this->app->environment('production')) {
            DB::ProhibitDestructiveCommands();
        }

        // Make sure all models are strict
        Model::shouldBeStrict();

        // Only users who are admin can access pulse in production
        Gate::define('viewPulse', function (User $user) {
            return $user->isAdmin();
        });
        // Login attempts limiter
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Admin routes limiter
        RateLimiter::for('admin', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('web', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });
    }
}
