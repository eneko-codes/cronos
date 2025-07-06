<?php

use App\Actions\UpdateNotificationPreferencesAction;
use App\Enums\NotificationType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can mute all notifications for a user', function (): void {
    $user = User::factory()->create();
    $action = app(UpdateNotificationPreferencesAction::class);
    $action->muteAll($user, $user->id, true);
    $user->refresh();
    expect($user->muted_notifications)->toBeTrue();
});

it('can toggle a notification type for a user', function (): void {
    $user = User::factory()->create();
    $type = NotificationType::cases()[0];
    $action = app(UpdateNotificationPreferencesAction::class);
    // Ensure preference exists or let the action create it
    $action->toggleType($user, $user->id, $type, false);
    $pref = $user->notificationPreferences()->where('notification_type', $type->value)->first();
    expect($pref->enabled)->toBeFalse();
});
