<?php

declare(strict_types=1);

namespace App\Providers;

use App\Clients\DesktimeApiClient;
use App\Clients\OdooApiClient;
use App\Clients\ProofhubApiClient;
use App\Clients\SystemPinApiClient;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;
use Laravel\Telescope\TelescopeServiceProvider as TelescopeServiceProviderBase;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register OdooApiClient
        $this->app->singleton(OdooApiClient::class, function (Application $app) {
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
        $this->app->singleton(ProofhubApiClient::class, function (Application $app) {
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
        $this->app->singleton(DesktimeApiClient::class, function (Application $app) {
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
        $this->app->singleton(SystemPinApiClient::class, function (Application $app) {
            /** @var ConfigRepository $config */
            $config = $app['config'];
            $systempinConfig = $config->get('services.systempin');

            if (! isset($systempinConfig['base_url'], $systempinConfig['api_key'])) {
                throw new InvalidArgumentException('SystemPin service configuration is missing or incomplete.');
            }

            return new SystemPinApiClient(
                baseUrl: $systempinConfig['base_url'],
                apiKey: $systempinConfig['api_key']
            );
        });

        // Register TelescopeServiceProvider if the environment is local
        if ($this->app->environment('local') && class_exists(TelescopeServiceProviderBase::class)) {
            $this->app->register(TelescopeServiceProviderBase::class);
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

        // Configure default password validation rules
        // This ensures consistent password requirements across the application
        Password::defaults(function () {
            return Password::min(16)
                ->mixedCase()
                ->numbers()
                ->symbols();
        });
    }
}
