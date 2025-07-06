<?php

use App\Actions\GetNotificationPreferencesAction;
use App\Enums\NotificationType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns all notification preferences for a user', function (): void {
    $user = User::factory()->create();
    $action = app(GetNotificationPreferencesAction::class);
    $result = $action->execute($user, $user->id);

    // Only expect types the user is allowed to see (skip admin-only if not admin)
    $expectedTypes = collect(NotificationType::cases())
        ->filter(fn ($type) => ! $type->isAdminOnly() || $user->isAdmin())
        ->pluck('value')
        ->all();

    expect(array_keys($result['user_individual']))->toEqualCanonicalizing($expectedTypes);
    expect($user->notificationPreferences()->count())->toBe(count($expectedTypes));
});
