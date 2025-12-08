<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Models\UserExternalIdentity;
use App\Policies\NotificationPreferencePolicy;
use App\Policies\UserExternalIdentityPolicy;
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
        UserExternalIdentity::class => UserExternalIdentityPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // =====================================================================
        // Role-based Gates (for navigation and route access)
        // =====================================================================

        // Gate: Access user management section (users list, user modals)
        // Available to: Admins + Maintenance users
        Gate::define('accessUserManagement', fn (User $user): bool => $user->isAdmin() || $user->isMaintenance());

        // Gate: Access admin-only routes (projects, schedules, leaves, admin settings)
        // Available to: Admins only
        Gate::define('accessAdminRoutes', fn (User $user): bool => $user->isAdmin());

        // Gate: View another user's dashboard
        // Available to: Admins can view any, others can only view their own
        Gate::define('viewUserDashboard', fn (User $authUser, User $targetUser): bool => $authUser->isAdmin() || $authUser->id === $targetUser->id);

        // =====================================================================
        // Notification preference gates (not model-based)
        // =====================================================================

        Gate::define('manageGlobalSettings', [NotificationPreferencePolicy::class, 'manageGlobalSettings']);
        Gate::define('viewGlobalSettings', [NotificationPreferencePolicy::class, 'viewGlobalSettings']);
        Gate::define('viewUserPreferences', [NotificationPreferencePolicy::class, 'viewUserPreferences']);
        Gate::define('updateUserPreferences', [NotificationPreferencePolicy::class, 'updateUserPreferences']);
        Gate::define('muteUserNotifications', [NotificationPreferencePolicy::class, 'muteUserNotifications']);
        Gate::define('accessSettingsPage', [NotificationPreferencePolicy::class, 'accessSettingsPage']);
        Gate::define('accessPreferencesSidebar', [NotificationPreferencePolicy::class, 'accessPreferencesSidebar']);
    }
}
