<?php

declare(strict_types=1);

use App\Enums\RoleType;
use App\Models\User;
use App\Notifications\AdminPromotionNotification;
use Illuminate\Database\Eloquent\Model;

beforeEach(function (): void {
    Model::unguard();
});

afterEach(function (): void {
    Model::reguard();
});

describe('User::routeNotificationForSlack', function (): void {
    it('returns null when neither per-user channel nor global default is configured', function (): void {
        config(['services.slack.notifications.channel' => null]);
        $user = User::create([
            'name' => 'No Slack User',
            'email' => 'noslack@test.com',
            'user_type' => RoleType::User,
            'is_active' => true,
        ]);
        $notification = new AdminPromotionNotification($user);

        expect($user->routeNotificationForSlack($notification))->toBeNull();
    });

    it('falls back to the global default channel when the user has no slack_channel_id', function (): void {
        config(['services.slack.notifications.channel' => 'C0GLOBAL']);
        $user = User::create([
            'name' => 'Fallback User',
            'email' => 'fallback@test.com',
            'user_type' => RoleType::User,
            'is_active' => true,
        ]);
        $notification = new AdminPromotionNotification($user);

        expect($user->routeNotificationForSlack($notification))->toBe('C0GLOBAL');
    });

    it('prefers the per-user slack_channel_id over the global default', function (): void {
        config(['services.slack.notifications.channel' => 'C0GLOBAL']);
        $user = User::create([
            'name' => 'Direct User',
            'email' => 'direct@test.com',
            'slack_channel_id' => 'D0PERUSER',
            'user_type' => RoleType::User,
            'is_active' => true,
        ]);
        $notification = new AdminPromotionNotification($user);

        expect($user->routeNotificationForSlack($notification))->toBe('D0PERUSER');
    });

    it('treats an empty string slack_channel_id like null and falls back to the global', function (): void {
        config(['services.slack.notifications.channel' => 'C0GLOBAL']);
        $user = User::create([
            'name' => 'Empty User',
            'email' => 'empty@test.com',
            'slack_channel_id' => '',
            'user_type' => RoleType::User,
            'is_active' => true,
        ]);
        $notification = new AdminPromotionNotification($user);

        expect($user->routeNotificationForSlack($notification))->toBe('C0GLOBAL');
    });
});
