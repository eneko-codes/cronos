<?php

use App\Actions\UpdateGlobalNotificationPreferencesAction;
use App\Enums\NotificationType;
use App\Models\GlobalNotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can toggle the global master switch', function (): void {
    $user = User::factory()->admin()->create();
    $action = app(UpdateGlobalNotificationPreferencesAction::class);
    $action->toggleMaster($user, false);
    $pref = GlobalNotificationPreference::first();
    expect($pref->enabled)->toBeFalse();
});

it('can toggle a global notification type', function (): void {
    $user = User::factory()->admin()->create();
    $type = NotificationType::cases()[0];
    $action = app(UpdateGlobalNotificationPreferencesAction::class);
    $action->toggleType($user, $type, false);
    $pref = GlobalNotificationPreference::where('notification_type', $type->value)->first();
    expect($pref->enabled)->toBeFalse();
});
