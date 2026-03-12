<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;

class MacroServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerQueryBuilderMacros();
        $this->registerHttpClientMacros();
        $this->registerResponseMacros();
    }

    /**
     * Register Query Builder macros for common query patterns.
     */
    private function registerQueryBuilderMacros(): void
    {
        $this->registerActiveUsersMacro();
        $this->registerCurrentlyEffectiveMacro();
    }

    /**
     * Register a macro to filter active users.
     *
     * Usage:
     * User::active()->get()
     * User::active()->count()
     */
    private function registerActiveUsersMacro(): void
    {
        EloquentBuilder::macro('active', function () {
            /** @phpstan-ignore-next-line */
            return $this->where('is_active', true);
        });
    }

    /**
     * Register a macro to filter records that are currently effective.
     *
     * This macro checks if a date column (typically 'effective_until') is null
     * or greater than or equal to a reference date, indicating the record
     * is currently active/effective.
     *
     * Usage:
     * $query->currentlyEffective('effective_until', $now)
     * $query->currentlyEffective('expires_at') // Uses now() as default
     */
    private function registerCurrentlyEffectiveMacro(): void
    {
        $macro = function (string $dateColumn = 'effective_until', $referenceDate = null) {
            $referenceDate = $referenceDate ?? now();

            /** @phpstan-ignore-next-line */
            return $this->where(function ($query) use ($dateColumn, $referenceDate): void {
                $query->whereNull($dateColumn)
                    ->orWhere($dateColumn, '>=', $referenceDate);
            });
        };

        Builder::macro('currentlyEffective', $macro);
        EloquentBuilder::macro('currentlyEffective', $macro);
    }

    /**
     * Register HTTP Client macros for external API integrations.
     */
    private function registerHttpClientMacros(): void
    {
        $this->registerProofhubHttpMacro();
        $this->registerOdooHttpMacro();
        $this->registerDesktimeHttpMacro();
        $this->registerSystemPinHttpMacro();
    }

    /**
     * Register HTTP macro for ProofHub API requests.
     *
     * Usage:
     * Http::proofhub()->get($url, $params)
     * Http::proofhub()->post($url, $data)
     */
    private function registerProofhubHttpMacro(): void
    {
        Http::macro('proofhub', function () {
            return Http::withHeaders([
                'X-API-KEY' => config('services.proofhub.api_key'),
                'Accept' => 'application/json',
                'User-Agent' => 'CronosApp',
            ])->timeout(60);
        });
    }

    /**
     * Register HTTP macro for Odoo API requests.
     *
     * Usage:
     * Http::odoo()->post('/jsonrpc', $payload)
     */
    private function registerOdooHttpMacro(): void
    {
        Http::macro('odoo', function () {
            return Http::withOptions(['verify' => false])
                ->baseUrl(config('services.odoo.base_url'))
                ->timeout(30)
                ->acceptJson();
        });
    }

    /**
     * Register HTTP macro for DeskTime API requests.
     *
     * Usage:
     * Http::desktime()->get($endpoint, $params)
     */
    private function registerDesktimeHttpMacro(): void
    {
        Http::macro('desktime', function () {
            return Http::baseUrl(config('services.desktime.base_url'))
                ->timeout(30);
        });
    }

    /**
     * Register HTTP macro for SystemPin API requests.
     *
     * Usage:
     * Http::systempin()->get($endpoint, $params)
     */
    private function registerSystemPinHttpMacro(): void
    {
        Http::macro('systempin', function () {
            return Http::withHeaders([
                'Authorization' => 'Bearer '.config('services.systempin.api_key'),
            ])
                ->withOptions([
                    'verify' => false, // Disable SSL verification for self-signed certificates
                    'timeout' => 30,
                ])
                ->baseUrl(config('services.systempin.base_url'));
        });
    }

    /**
     * Register Response macros for common response patterns.
     */
    private function registerResponseMacros(): void
    {
        $this->registerToastResponseMacro();
    }

    /**
     * Register a response macro for toast notifications.
     *
     * This macro creates a redirect response with a toast notification
     * that can be displayed in the frontend.
     *
     * Usage:
     * return response()->toast('User updated successfully', 'success')
     * return response()->toast('Operation failed', 'error')
     * return response()->toast('Warning message', 'warning')
     */
    private function registerToastResponseMacro(): void
    {
        Response::macro('toast', function (string $message, string $variant = 'info', ?string $redirectTo = null) {
            $redirect = $redirectTo ? redirect($redirectTo) : redirect()->back();

            return $redirect->with('toast', [
                'message' => $message,
                'variant' => $variant,
            ]);
        });
    }
}
