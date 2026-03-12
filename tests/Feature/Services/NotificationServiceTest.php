<?php

declare(strict_types=1);

use App\Enums\NotificationType;
use App\Enums\RoleType;
use App\Models\GlobalNotificationPreference;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Model;

beforeEach(function (): void {
    Model::unguard();

    // Create default global preferences (using updateOrCreate to avoid duplicates)
    GlobalNotificationPreference::updateOrCreate(
        ['notification_type' => 'global_master'],
        ['enabled' => true]
    );

    foreach (NotificationType::cases() as $type) {
        // Only create preferences for non-mandatory notifications
        if (! $type->isMandatory()) {
            GlobalNotificationPreference::updateOrCreate(
                ['notification_type' => $type->value],
                ['enabled' => $type->defaultEnabled()]
            );
        }
    }
});

afterEach(function (): void {
    Model::reguard();
});

describe('NotificationService eligibility checks', function (): void {
    it('returns false when global notifications are disabled', function (): void {
        $service = app(NotificationService::class);
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@test.com',
            'is_active' => true,
        ]);

        // Disable global master switch
        GlobalNotificationPreference::where('notification_type', 'global_master')
            ->update(['enabled' => false]);

        $eligible = $service->isEligible(NotificationType::ScheduleChange, $user);

        expect($eligible)->toBeFalse();
    });

    it('returns false when specific type is disabled globally', function (): void {
        $service = app(NotificationService::class);
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@test.com',
            'is_active' => true,
        ]);

        // Disable specific type globally
        GlobalNotificationPreference::where('notification_type', NotificationType::ScheduleChange->value)
            ->update(['enabled' => false]);

        $eligible = $service->isEligible(NotificationType::ScheduleChange, $user);

        expect($eligible)->toBeFalse();
    });

    it('returns false when user is archived', function (): void {
        $service = app(NotificationService::class);
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@test.com',
            'is_active' => false, // Archived
        ]);

        $eligible = $service->isEligible(NotificationType::ScheduleChange, $user);

        expect($eligible)->toBeFalse();
    });

    it('returns false when user has muted all notifications', function (): void {
        $service = app(NotificationService::class);
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@test.com',
            'is_active' => true,
            'muted_notifications' => true,
        ]);

        $eligible = $service->isEligible(NotificationType::ScheduleChange, $user);

        expect($eligible)->toBeFalse();
    });

    it('returns false for admin-only notifications when user is not admin', function (): void {
        $service = app(NotificationService::class);
        $user = User::create([
            'name' => 'Regular User',
            'email' => 'test@test.com',
            'is_active' => true,
            'user_type' => RoleType::User,
        ]);

        $eligible = $service->isEligible(NotificationType::AdminPromotion, $user);

        expect($eligible)->toBeFalse();
    });

    it('returns false for maintenance-only notifications when user is not maintenance', function (): void {
        $service = app(NotificationService::class);
        $user = User::create([
            'name' => 'Regular User',
            'email' => 'test@test.com',
            'is_active' => true,
            'user_type' => RoleType::User,
        ]);

        $eligible = $service->isEligible(NotificationType::ApiDown, $user);

        expect($eligible)->toBeFalse();
    });

    it('respects user individual preference when set', function (): void {
        $service = app(NotificationService::class);
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@test.com',
            'is_active' => true,
        ]);

        // User disables this notification type (use updateOrCreate in case observer already created it)
        UserNotificationPreference::updateOrCreate(
            [
                'user_id' => $user->id,
                'notification_type' => NotificationType::ScheduleChange->value,
            ],
            ['enabled' => false]
        );

        $eligible = $service->isEligible(NotificationType::ScheduleChange, $user);

        expect($eligible)->toBeFalse();
    });

    it('falls back to default when user preference not set', function (): void {
        $service = app(NotificationService::class);
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@test.com',
            'is_active' => true,
        ]);

        // No user preference set, should use default
        $eligible = $service->isEligible(NotificationType::ScheduleChange, $user);

        expect($eligible)->toBe(NotificationType::ScheduleChange->defaultEnabled());
    });

    it('ignores user preferences for mandatory notifications', function (): void {
        $service = app(NotificationService::class);
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'is_active' => true,
            'user_type' => RoleType::Admin,
        ]);

        // Try to disable UserPromotedToAdmin (mandatory notification)
        UserNotificationPreference::updateOrCreate(
            [
                'user_id' => $admin->id,
                'notification_type' => NotificationType::UserPromotedToAdmin->value,
            ],
            ['enabled' => false]
        );

        // Should still be eligible (mandatory notifications ignore user preferences)
        $eligible = $service->isEligible(NotificationType::UserPromotedToAdmin, $admin);

        expect($eligible)->toBeTrue();
    });

    it('returns true for mandatory notifications even when global disabled', function (): void {
        $service = app(NotificationService::class);
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@test.com',
            'is_active' => true,
        ]);

        // Try to disable WelcomeNewUser globally (mandatory, should not affect eligibility)
        GlobalNotificationPreference::updateOrCreate(
            ['notification_type' => NotificationType::WelcomeNewUser->value],
            ['enabled' => false]
        );

        $eligible = $service->isEligible(NotificationType::WelcomeNewUser, $user);

        expect($eligible)->toBeTrue();
    });

    it('returns comprehensive preferences for a user', function (): void {
        $service = app(NotificationService::class);
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@test.com',
            'is_active' => true,
            'muted_notifications' => false,
        ]);

        $prefs = $service->getPreferences($user, $user->id);

        expect($prefs)->toHaveKeys([
            'global_enabled',
            'global_types',
            'user_mute_all',
            'user_individual',
            'available_types',
            'eligibility',
        ]);

        expect($prefs['global_enabled'])->toBeTrue();
        expect($prefs['user_mute_all'])->toBeFalse();
        expect($prefs['global_types'])->toBeArray();
        expect($prefs['user_individual'])->toBeArray();
        expect($prefs['eligibility'])->toBeArray();
    });
});

describe('NotificationService notification dispatching', function (): void {
    it('sends notifications to maintenance users with eager loaded preferences', function (): void {
        $maintenance1 = User::create([
            'name' => 'Maintenance 1',
            'email' => 'maint1@test.com',
            'is_active' => true,
            'user_type' => RoleType::Maintenance,
        ]);

        $maintenance2 = User::create([
            'name' => 'Maintenance 2',
            'email' => 'maint2@test.com',
            'is_active' => true,
            'user_type' => RoleType::Maintenance,
        ]);

        // Create inactive maintenance user (should not receive)
        User::create([
            'name' => 'Inactive Maintenance',
            'email' => 'inactive@test.com',
            'is_active' => false,
            'user_type' => RoleType::Maintenance,
        ]);

        $service = app(NotificationService::class);
        $notification = new \App\Notifications\ApiDownNotification('Test Service', 'Test error message');

        Notification::fake();

        $service->notifyMaintenanceUsers($notification);

        Notification::assertSentTo([$maintenance1, $maintenance2], \App\Notifications\ApiDownNotification::class);
        Notification::assertNotSentTo(
            User::where('email', 'inactive@test.com')->first(),
            \App\Notifications\ApiDownNotification::class
        );
    });

    it('sends notifications to admin users excluding specified user', function (): void {
        $admin1 = User::create([
            'name' => 'Admin 1',
            'email' => 'admin1@test.com',
            'is_active' => true,
            'user_type' => RoleType::Admin,
        ]);

        $admin2 = User::create([
            'name' => 'Admin 2',
            'email' => 'admin2@test.com',
            'is_active' => true,
            'user_type' => RoleType::Admin,
        ]);

        $service = app(NotificationService::class);
        $notification = new \App\Notifications\AdminPromotionNotification($admin1, $admin1);

        Notification::fake();

        // Exclude admin1 from recipients
        $service->notifyAdminUsers($notification, excludeUserId: $admin1->id);

        Notification::assertNotSentTo($admin1, \App\Notifications\AdminPromotionNotification::class);
        Notification::assertSentTo($admin2, \App\Notifications\AdminPromotionNotification::class);
    });

    it('sends notification to single user', function (): void {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@test.com',
            'is_active' => true,
        ]);

        $service = app(NotificationService::class);
        $notification = new \App\Notifications\ScheduleChangeNotification($user, null, null);

        Notification::fake();

        $service->notifyUser($user, $notification);

        Notification::assertSentTo($user, \App\Notifications\ScheduleChangeNotification::class);
    });
});
