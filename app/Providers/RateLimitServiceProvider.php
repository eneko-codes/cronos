<?php

declare(strict_types=1);

namespace App\Providers;

use App\Notifications\ApiDownNotification;
use App\Notifications\FailedLoginAttemptNotification;
use App\Notifications\UnlinkedPlatformUserNotification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class RateLimitServiceProvider extends ServiceProvider
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
        $this->registerAuthenticationRateLimiters();
        $this->registerNotificationRateLimiters();
    }

    /**
     * Register rate limiters for authentication-related routes.
     */
    private function registerAuthenticationRateLimiters(): void
    {
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

        // Email verification resend rate limiter (1 per minute per user)
        // Used by verification.send route for manual resend requests
        // Note: Route uses 'auth' middleware, so user is guaranteed to be authenticated
        RateLimiter::for('email-verification-resend', function (Request $request) {
            return Limit::perMinute(1)
                ->by($request->user()?->id ?? $request->ip())
                ->response(function (Request $request): \Illuminate\Http\RedirectResponse {
                    return redirect()->back()->with('toast', [
                        'message' => 'Please wait 1 minute before requesting another verification email.',
                        'variant' => 'warning',
                    ]);
                });
        });
    }

    /**
     * Register rate limiters for notification-related jobs.
     */
    private function registerNotificationRateLimiters(): void
    {
        // API down notification rate limiter (1 per 60 minutes per service per user)
        // Extracts service name and user ID from the queued notification job
        RateLimiter::for('api-down-notification', function (object $job) {
            // Access notification and notifiables from SendQueuedNotifications job
            $notification = $job->notification ?? null;
            $notifiables = $job->notifiables ?? null;

            if ($notification instanceof ApiDownNotification && $notifiables) {
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

        // Unlinked platform user rate limiter (1 per 24 hours per platform per user)
        // Extracts platform and user ID from the queued notification job
        // Notifications are now aggregated - one notification per platform per sync cycle
        RateLimiter::for('unlinked-platform-user', function (object $job) {
            // Access notification and notifiables from SendQueuedNotifications job
            $notification = $job->notification ?? null;
            $notifiables = $job->notifiables ?? null;

            if ($notification instanceof UnlinkedPlatformUserNotification && $notifiables) {
                // Get first notifiable (for single notifications, notifiables is a collection with one item)
                $notifiable = $notifiables->first();
                if ($notifiable) {
                    // Create composite key: platform + user ID for per-platform-per-user rate limiting
                    // Since notifications are aggregated, we only need platform + user, not individual external IDs
                    $key = "{$notification->platform->value}:{$notifiable->getKey()}";

                    return Limit::perMinutes(1440, 1)->by($key); // 24 hours = 1440 minutes
                }
            }

            // Fallback: use job class name if we can't extract notification properties
            return Limit::perMinutes(1440, 1)->by(get_class($job));
        });

        // Failed login attempt notification rate limiter (1 per 60 minutes per email+IP per user)
        // Prevents spam when multiple failed attempts occur in quick succession
        RateLimiter::for('failed-login-attempt-notification', function (object $job) {
            // Access notification and notifiables from SendQueuedNotifications job
            $notification = $job->notification ?? null;
            $notifiables = $job->notifiables ?? null;

            if ($notification instanceof FailedLoginAttemptNotification && $notifiables) {
                // Get first notifiable (for single notifications, notifiables is a collection with one item)
                $notifiable = $notifiables->first();
                if ($notifiable) {
                    // Create composite key: email + IP + user ID for per-email-per-IP-per-user rate limiting
                    $key = "{$notification->email}:{$notification->ipAddress}:{$notifiable->getKey()}";

                    return Limit::perMinutes(60, 1)->by($key);
                }
            }

            // Fallback: use job class name if we can't extract notification properties
            return Limit::perMinutes(60, 1)->by(get_class($job));
        });
    }
}
