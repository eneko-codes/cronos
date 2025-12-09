<?php

declare(strict_types=1);

namespace App\Providers;

use App\Clients\DesktimeApiClient;
use App\Clients\OdooApiClient;
use App\Clients\ProofhubApiClient;
use App\Clients\SystemPinApiClient;
use App\Models\User;
use App\Services\SyncStatusService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
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

        // Only users who are admin can access pulse in production
        Gate::define('viewPulse', function (User $user) {
            return $user->isAdmin();
        });

        // Login attempts limiter
        RateLimiter::for('login', function (Request $request) {
            $maxAttempts = config('rate-limiting.login.max_attempts');
            $decayMinutes = config('rate-limiting.login.decay_minutes');

            return Limit::perMinutes($decayMinutes, $maxAttempts)
                ->by($request->ip())
                ->response(function (Request $request) use ($decayMinutes): \Illuminate\Http\RedirectResponse {
                    return redirect()->back()
                        ->withInput($request->except(['password']))
                        ->withErrors(['rate_limit' => $decayMinutes]);
                });
        });

        // Admin routes limiter
        RateLimiter::for('admin', function (Request $request) {
            $maxAttempts = config('rate-limiting.admin.max_attempts');
            $decayMinutes = config('rate-limiting.admin.decay_minutes');

            return Limit::perMinutes($decayMinutes, $maxAttempts)
                ->by($request->user()?->id ?: $request->ip());
        });

        // Web routes limiter
        RateLimiter::for('web', function (Request $request) {
            $maxAttempts = config('rate-limiting.web.max_attempts');
            $decayMinutes = config('rate-limiting.web.decay_minutes');

            return Limit::perMinutes($decayMinutes, $maxAttempts)
                ->by($request->user()?->id ?: $request->ip());
        });

        // Password reset attempts limiter
        RateLimiter::for('password-reset', function (Request $request) {
            $maxAttempts = config('rate-limiting.password-reset.max_attempts');
            $decayMinutes = config('rate-limiting.password-reset.decay_minutes');

            return Limit::perMinutes($decayMinutes, $maxAttempts)
                ->by($request->ip())
                ->response(function (Request $request) use ($decayMinutes): \Illuminate\Http\RedirectResponse {
                    return redirect()->back()
                        ->withInput($request->except(['password', 'password_confirmation']))
                        ->withErrors(['rate_limit' => "Too many password reset attempts. Please try again in {$decayMinutes} minute(s)."]);
                });
        });

        // Forgot password requests limiter
        RateLimiter::for('forgot-password', function (Request $request) {
            $maxAttempts = config('rate-limiting.forgot-password.max_attempts');
            $decayMinutes = config('rate-limiting.forgot-password.decay_minutes');

            return Limit::perMinutes($decayMinutes, $maxAttempts)
                ->by($request->ip())
                ->response(function (Request $request) use ($decayMinutes): \Illuminate\Http\RedirectResponse {
                    return redirect()->back()
                        ->withInput($request->except(['password']))
                        ->withErrors(['rate_limit' => "Too many password reset requests. Please try again in {$decayMinutes} minute(s)."]);
                });
        });

        // Password setup attempts limiter
        RateLimiter::for('password-setup', function (Request $request) {
            $maxAttempts = config('rate-limiting.password-setup.max_attempts');
            $decayMinutes = config('rate-limiting.password-setup.decay_minutes');

            return Limit::perMinutes($decayMinutes, $maxAttempts)
                ->by($request->ip())
                ->response(function (Request $request) use ($decayMinutes): \Illuminate\Http\RedirectResponse {
                    return redirect()->back()
                        ->withInput($request->except(['password', 'password_confirmation']))
                        ->withErrors(['rate_limit' => "Too many password setup attempts. Please try again in {$decayMinutes} minute(s)."]);
                });
        });

        // Email verification rate limiter (1 per 5 minutes per user)
        RateLimiter::for('email-verification', function (object $notifiable) {
            return Limit::perMinutes(5, 1)->by($notifiable->id);
        });

        // API down notification rate limiter (1 per 60 minutes per service per user)
        // Extracts service name and user ID from the queued notification job
        RateLimiter::for('api-down-notification', function (object $job) {
            // Access notification and notifiables from SendQueuedNotifications job
            $notification = $job->notification ?? null;
            $notifiables = $job->notifiables ?? null;

            if ($notification instanceof \App\Notifications\ApiDownNotification && $notifiables) {
                // Get first notifiable (for single notifications, notifiables is a collection with one item)
                $notifiable = $notifiables->first();
                if ($notifiable) {
                    // Create composite key: service name + user ID for per-service-per-user rate limiting
                    $key = "{$notification->serviceName}:{$notifiable->getKey()}";

                    return Limit::perMinutes(60, 1)->by($key);
                }
            }

            // Fallback: use job class name if we can't extract notification properties
            return Limit::perMinutes(60, 1)->by(get_class($job));
        });

        // Unlinked platform user rate limiter (1 per 24 hours per platform per external ID per user)
        // Extracts platform, external ID, and user ID from the queued notification job
        RateLimiter::for('unlinked-platform-user', function (object $job) {
            // Access notification and notifiables from SendQueuedNotifications job
            $notification = $job->notification ?? null;
            $notifiables = $job->notifiables ?? null;

            if ($notification instanceof \App\Notifications\UnlinkedPlatformUserNotification && $notifiables) {
                // Get first notifiable (for single notifications, notifiables is a collection with one item)
                $notifiable = $notifiables->first();
                if ($notifiable) {
                    // Create composite key: platform + external ID + user ID for per-platform-per-external-user-per-user rate limiting
                    $key = "{$notification->platform->value}:{$notification->externalId}:{$notifiable->getKey()}";

                    return Limit::perMinutes(1440, 1)->by($key); // 24 hours = 1440 minutes
                }
            }

            // Fallback: use job class name if we can't extract notification properties
            return Limit::perMinutes(1440, 1)->by(get_class($job));
        });

        // Register sync job event listeners for per-platform status tracking
        $this->registerSyncJobEventListeners();
    }

    /**
     * Register event listeners for sync jobs to track per-platform sync status.
     *
     * Uses Laravel's native Queue events to track when sync jobs start, complete,
     * or fail, storing the status in cache via SyncStatusService.
     */
    private function registerSyncJobEventListeners(): void
    {
        $syncStatusService = app(SyncStatusService::class);

        // Track when a sync job starts processing
        Queue::before(function (JobProcessing $event) use ($syncStatusService): void {
            $jobClass = $this->getJobClassFromPayload($event->job->payload());

            if ($jobClass === null || ! $this->isSyncJob($jobClass)) {
                return;
            }

            $platform = SyncStatusService::getPlatformFromJob($jobClass);

            if ($platform !== null) {
                $syncStatusService->recordInProgress(
                    $platform,
                    SyncStatusService::getShortJobName($jobClass)
                );
            }
        });

        // Track when a sync job completes successfully
        Queue::after(function (JobProcessed $event) use ($syncStatusService): void {
            $jobClass = $this->getJobClassFromPayload($event->job->payload());

            if ($jobClass === null || ! $this->isSyncJob($jobClass)) {
                return;
            }

            $platform = SyncStatusService::getPlatformFromJob($jobClass);

            if ($platform !== null) {
                $syncStatusService->recordSuccess(
                    $platform,
                    SyncStatusService::getShortJobName($jobClass)
                );
            }
        });

        // Track when a sync job fails
        Queue::failing(function (JobFailed $event) use ($syncStatusService): void {
            $jobClass = $this->getJobClassFromPayload($event->job->payload());

            if ($jobClass === null || ! $this->isSyncJob($jobClass)) {
                return;
            }

            $platform = SyncStatusService::getPlatformFromJob($jobClass);

            if ($platform !== null) {
                $syncStatusService->recordFailure(
                    $platform,
                    SyncStatusService::getShortJobName($jobClass),
                    $event->exception->getMessage()
                );
            }
        });
    }

    /**
     * Get the job class name from the queue payload.
     *
     * Uses the displayName which contains the full class name,
     * avoiding the need to unserialize encrypted jobs.
     *
     * @param  array<string, mixed>  $payload  The job payload
     * @return string|null The job class name or null if not found
     */
    private function getJobClassFromPayload(array $payload): ?string
    {
        return $payload['displayName'] ?? null;
    }

    /**
     * Check if a job class is a sync job (in the App\Jobs\Sync namespace).
     *
     * @param  string  $jobClass  The job class name
     * @return bool True if it's a sync job
     */
    private function isSyncJob(string $jobClass): bool
    {
        return str_starts_with($jobClass, 'App\\Jobs\\Sync\\');
    }
}
