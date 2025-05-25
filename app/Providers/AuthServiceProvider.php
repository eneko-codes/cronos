<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Policies\NotificationPreferencePolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Notification preference gates (not model-based)
        Gate::define('manageGlobalSettings', [NotificationPreferencePolicy::class, 'manageGlobalSettings']);
        Gate::define('viewGlobalSettings', [NotificationPreferencePolicy::class, 'viewGlobalSettings']);
        Gate::define('viewUserPreferences', [NotificationPreferencePolicy::class, 'viewUserPreferences']);
        Gate::define('updateUserPreferences', [NotificationPreferencePolicy::class, 'updateUserPreferences']);
        Gate::define('muteUserNotifications', [NotificationPreferencePolicy::class, 'muteUserNotifications']);
        Gate::define('accessSettingsPage', [NotificationPreferencePolicy::class, 'accessSettingsPage']);
        Gate::define('accessPreferencesSidebar', [NotificationPreferencePolicy::class, 'accessPreferencesSidebar']);
    }
}
