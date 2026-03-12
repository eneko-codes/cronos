<?php

declare(strict_types=1);

use App\Enums\NotificationGroup;
use App\Enums\NotificationType;
use App\Enums\RoleType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

beforeEach(function (): void {
    Model::unguard();
});

afterEach(function (): void {
    Model::reguard();
});

describe('NotificationType enum', function (): void {
    it('has all expected notification types', function (): void {
        $expected = [
            'schedule_change',
            'schedule_starting',
            'leave_reminder',
            'leave_status_change',
            'api_down',
            'unlinked_platform_user',
            'failed_login_attempt',
            'admin_promotion',
            'admin_demotion',
            'maintenance_promotion',
            'maintenance_demotion',
            'user_archived_admin',
            'user_reactivated_admin',
            'user_do_not_track_admin',
            'user_tracking_enabled_admin',
            'welcome_new_user',
            'user_promoted_to_admin',
            'user_promoted_to_maintenance',
            'user_demoted_from_admin',
            'user_demoted_from_maintenance',
            'user_archived',
            'user_reactivated',
            'user_do_not_track',
            'user_tracking_enabled',
            'account_lockout',
        ];

        $actual = array_map(fn ($type) => $type->value, NotificationType::cases());

        expect($actual)->toEqual($expected);
    });

    it('returns correct labels for all types', function (): void {
        expect(NotificationType::ScheduleChange->label())->toBe('Schedule Updated');
        expect(NotificationType::ApiDown->label())->toBe('Service Outage');
        expect(NotificationType::UserPromotedToAdmin->label())->toBe('You\'re Now an Admin');
        expect(NotificationType::AccountLockout->label())->toBe('Account Temporarily Locked');
    });

    it('returns correct descriptions for all types', function (): void {
        expect(NotificationType::ScheduleChange->description())
            ->toContain('work schedule');
        expect(NotificationType::FailedLoginAttempt->description())
            ->toContain('failed login');
    });

    it('correctly identifies admin-only notifications', function (): void {
        // Admin-only notifications
        expect(NotificationType::AdminPromotion->isAdminOnly())->toBeTrue();
        expect(NotificationType::AdminDemotion->isAdminOnly())->toBeTrue();
        expect(NotificationType::UserArchivedAdmin->isAdminOnly())->toBeTrue();
        expect(NotificationType::UserPromotedToAdmin->isAdminOnly())->toBeTrue();

        // Not admin-only
        expect(NotificationType::ScheduleChange->isAdminOnly())->toBeFalse();
        expect(NotificationType::ApiDown->isAdminOnly())->toBeFalse();
    });

    it('correctly identifies maintenance-only notifications', function (): void {
        // Maintenance-only notifications
        expect(NotificationType::ApiDown->isMaintenanceOnly())->toBeTrue();
        expect(NotificationType::UnlinkedPlatformUser->isMaintenanceOnly())->toBeTrue();
        expect(NotificationType::FailedLoginAttempt->isMaintenanceOnly())->toBeTrue();
        expect(NotificationType::UserPromotedToMaintenance->isMaintenanceOnly())->toBeTrue();

        // Not maintenance-only
        expect(NotificationType::ScheduleChange->isMaintenanceOnly())->toBeFalse();
        expect(NotificationType::AdminPromotion->isMaintenanceOnly())->toBeFalse();
    });

    it('correctly identifies mandatory notifications', function (): void {
        // Mandatory notifications (always sent, cannot be disabled)
        expect(NotificationType::WelcomeNewUser->isMandatory())->toBeTrue();
        expect(NotificationType::UserArchived->isMandatory())->toBeTrue();
        expect(NotificationType::UserReactivated->isMandatory())->toBeTrue();
        expect(NotificationType::UserDoNotTrack->isMandatory())->toBeTrue();
        expect(NotificationType::UserTrackingEnabled->isMandatory())->toBeTrue();
        expect(NotificationType::AccountLockout->isMandatory())->toBeTrue();
        expect(NotificationType::UserPromotedToAdmin->isMandatory())->toBeTrue();
        expect(NotificationType::UserPromotedToMaintenance->isMandatory())->toBeTrue();

        // Non-mandatory notifications (can be disabled by users/admins)
        expect(NotificationType::ScheduleChange->isMandatory())->toBeFalse();
        expect(NotificationType::AdminPromotion->isMandatory())->toBeFalse();
        expect(NotificationType::ApiDown->isMandatory())->toBeFalse();
    });

    it('returns correct default enabled states', function (): void {
        // All notifications are enabled by default
        expect(NotificationType::ScheduleChange->defaultEnabled())->toBeTrue();
        expect(NotificationType::LeaveStatusChange->defaultEnabled())->toBeTrue();
        expect(NotificationType::ApiDown->defaultEnabled())->toBeTrue();
        expect(NotificationType::LeaveReminder->defaultEnabled())->toBeTrue();
        expect(NotificationType::AdminPromotion->defaultEnabled())->toBeTrue();
        expect(NotificationType::UserArchivedAdmin->defaultEnabled())->toBeTrue();
    });

    it('correctly assigns notification groups', function (): void {
        // Personal group
        expect(NotificationType::ScheduleChange->group())->toBe(NotificationGroup::Personal);
        expect(NotificationType::LeaveReminder->group())->toBe(NotificationGroup::Personal);

        // Maintenance group
        expect(NotificationType::ApiDown->group())->toBe(NotificationGroup::Maintenance);
        expect(NotificationType::FailedLoginAttempt->group())->toBe(NotificationGroup::Maintenance);

        // Admin group
        expect(NotificationType::AdminPromotion->group())->toBe(NotificationGroup::Admin);
        expect(NotificationType::UserArchivedAdmin->group())->toBe(NotificationGroup::Admin);
    });

    it('filters notifications available for regular users', function (): void {
        $regularUser = User::create([
            'user_type' => RoleType::User,
            'name' => 'Regular User',
            'email' => 'regular@test.com',
            'user_type' => RoleType::User,
        ]);

        $available = NotificationType::availableForUser($regularUser);

        // Should include personal notifications
        expect($available)->toContain(NotificationType::ScheduleChange);
        expect($available)->toContain(NotificationType::LeaveReminder);

        // Should NOT include admin-only notifications
        expect($available)->not->toContain(NotificationType::AdminPromotion);
        expect($available)->not->toContain(NotificationType::UserArchivedAdmin);

        // Should NOT include maintenance-only notifications
        expect($available)->not->toContain(NotificationType::ApiDown);
        expect($available)->not->toContain(NotificationType::UnlinkedPlatformUser);
    });

    it('filters notifications available for admin users', function (): void {
        $adminUser = User::create([
            'user_type' => RoleType::User,
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'user_type' => RoleType::Admin,
        ]);

        $available = NotificationType::availableForUser($adminUser);

        // Should include personal notifications
        expect($available)->toContain(NotificationType::ScheduleChange);

        // Should include admin-only notifications
        expect($available)->toContain(NotificationType::AdminPromotion);
        expect($available)->toContain(NotificationType::UserArchivedAdmin);

        // Should NOT include maintenance-only notifications (admin != maintenance)
        expect($available)->not->toContain(NotificationType::ApiDown);
        expect($available)->not->toContain(NotificationType::UnlinkedPlatformUser);
    });

    it('filters notifications available for maintenance users', function (): void {
        $maintenanceUser = User::create([
            'user_type' => RoleType::User,
            'name' => 'Maintenance User',
            'email' => 'maintenance@test.com',
            'user_type' => RoleType::Maintenance,
        ]);

        $available = NotificationType::availableForUser($maintenanceUser);

        // Should include personal notifications
        expect($available)->toContain(NotificationType::ScheduleChange);

        // Should include maintenance-only notifications
        expect($available)->toContain(NotificationType::ApiDown);
        expect($available)->toContain(NotificationType::UnlinkedPlatformUser);

        // Should NOT include admin-only notifications
        expect($available)->not->toContain(NotificationType::AdminPromotion);
        expect($available)->not->toContain(NotificationType::UserArchivedAdmin);
    });

    it('groups notifications by category for admin user', function (): void {
        // Admin users get personal + admin groups (not maintenance)
        $adminUser = User::create([
            'user_type' => RoleType::Admin,
            'name' => 'Admin User',
            'email' => 'admin-groups@test.com',
        ]);

        $grouped = NotificationType::groupedForUser($adminUser);

        expect($grouped)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($grouped)->toHaveKeys(['personal', 'admin']);

        // Personal group should have personal notifications
        expect($grouped['personal'])->toContain(NotificationType::ScheduleChange);

        // Admin group should have admin notifications
        expect($grouped['admin'])->toContain(NotificationType::AdminPromotion);
    });

    it('groups notifications by category for maintenance user', function (): void {
        // Maintenance users get personal + maintenance groups (not admin)
        $maintenanceUser = User::create([
            'user_type' => RoleType::Maintenance,
            'name' => 'Maintenance User',
            'email' => 'maintenance-groups@test.com',
        ]);

        $grouped = NotificationType::groupedForUser($maintenanceUser);

        expect($grouped)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($grouped)->toHaveKeys(['personal', 'maintenance']);

        // Personal group should have personal notifications
        expect($grouped['personal'])->toContain(NotificationType::ScheduleChange);

        // Maintenance group should have maintenance notifications
        expect($grouped['maintenance'])->toContain(NotificationType::ApiDown);
    });

    it('groups notifications for specific user', function (): void {
        $regularUser = User::create([
            'user_type' => RoleType::User,
            'name' => 'Regular User',
            'email' => 'regular@test.com',
            'user_type' => RoleType::User,
        ]);

        $grouped = NotificationType::groupedForUser($regularUser);

        // Should only have personal group for regular users
        expect($grouped)->toHaveKey('personal');
        expect($grouped)->not->toHaveKey('admin');
        expect($grouped)->not->toHaveKey('maintenance');
    });

    it('returns config array with all properties', function (): void {
        $config = NotificationType::toConfigArray();

        expect($config)->toBeArray();
        expect($config)->toHaveKeys([
            'schedule_change',
            'api_down',
            'admin_promotion',
        ]);

        expect($config['schedule_change'])->toHaveKeys([
            'label',
            'description',
            'admin_only',
            'maintenance_only',
            'default_enabled',
        ]);

        expect($config['schedule_change']['label'])->toBe('Schedule Updated');
        expect($config['schedule_change']['admin_only'])->toBeFalse();
        expect($config['schedule_change']['default_enabled'])->toBeTrue();
    });
});
