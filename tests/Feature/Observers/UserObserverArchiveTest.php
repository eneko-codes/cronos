<?php

declare(strict_types=1);

use App\Enums\RoleType;
use App\Models\Category;
use App\Models\Project;
use App\Models\Schedule;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\UserAttendance;
use App\Models\UserLeave;
use App\Models\UserNotificationPreference;
use App\Models\UserSchedule;
use App\Notifications\WelcomeNewUserNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Model::unguard();
    Notification::fake(); // Don't actually dispatch role/archive notifications
});

afterEach(function (): void {
    Model::reguard();
});

/**
 * Tests UserObserver's archive flow and the deleteUserData() transaction wrap (audit critical #5).
 *
 * The observer fires deleteUserData() when is_active flips false or do_not_track flips true.
 * deleteUserData removes data across 8 related tables — must be atomic.
 */
describe('UserObserver archive flow', function (): void {
    it('purges all related data when a user is archived (is_active=false)', function (): void {
        $user = User::create([
            'name' => 'Active User',
            'email' => 'active@archive-test.com',
            'user_type' => RoleType::User,
            'is_active' => true,
        ]);

        // Seed related data across every relation deleteUserData touches
        $schedule = Schedule::factory()->create();
        UserSchedule::create([
            'user_id' => $user->id,
            'odoo_schedule_id' => $schedule->odoo_schedule_id,
            'effective_from' => now(),
            'effective_until' => null,
        ]);
        UserLeave::factory()->create(['user_id' => $user->id]);
        UserAttendance::factory()->create(['user_id' => $user->id]);
        $project = Project::factory()->create();
        TimeEntry::factory()->create([
            'user_id' => $user->id,
            'proofhub_project_id' => $project->proofhub_project_id,
        ]);
        UserNotificationPreference::create([
            'user_id' => $user->id,
            'notification_type' => 'schedule_change',
            'enabled' => true,
        ]);
        $project->users()->attach($user->id);
        $category = Category::create(['odoo_category_id' => 999, 'name' => 'Cat', 'active' => true]);
        $category->users()->attach($user->id);
        $task = Task::factory()->create(['proofhub_project_id' => $project->proofhub_project_id]);
        $task->users()->attach($user->id);

        // Archive
        $user->update(['is_active' => false]);

        expect(UserSchedule::where('user_id', $user->id)->count())->toBe(0)
            ->and(UserLeave::where('user_id', $user->id)->count())->toBe(0)
            ->and(UserAttendance::where('user_id', $user->id)->count())->toBe(0)
            ->and(TimeEntry::where('user_id', $user->id)->count())->toBe(0)
            ->and(UserNotificationPreference::where('user_id', $user->id)->count())->toBe(0)
            ->and(DB::table('project_user')->where('user_id', $user->id)->count())->toBe(0)
            ->and(DB::table('category_user')->where('user_id', $user->id)->count())->toBe(0)
            ->and(DB::table('task_user')->where('user_id', $user->id)->count())->toBe(0);

        // User row itself stays, just flagged inactive
        $user->refresh();
        expect($user->is_active)->toBeFalse();
    });

    it('purges all related data when do_not_track flips to true', function (): void {
        $user = User::create([
            'name' => 'Tracked User',
            'email' => 'tracked@archive-test.com',
            'user_type' => RoleType::User,
            'is_active' => true,
            'do_not_track' => false,
        ]);
        UserAttendance::factory()->create(['user_id' => $user->id]);
        TimeEntry::factory()->create([
            'user_id' => $user->id,
            'proofhub_project_id' => Project::factory()->create()->proofhub_project_id,
        ]);

        $user->update(['do_not_track' => true]);

        expect(UserAttendance::where('user_id', $user->id)->count())->toBe(0)
            ->and(TimeEntry::where('user_id', $user->id)->count())->toBe(0);
    });

    it('leaves data intact on a plain user update', function (): void {
        $user = User::create([
            'name' => 'Stable User',
            'email' => 'stable@archive-test.com',
            'user_type' => RoleType::User,
            'is_active' => true,
        ]);
        UserAttendance::factory()->create(['user_id' => $user->id]);

        $user->update(['name' => 'New Name']);

        expect(UserAttendance::where('user_id', $user->id)->count())->toBe(1);
    });
});

describe('WelcomeNewUserNotification dispatch on create', function (): void {
    it('sends a welcome notification when a new user is created', function (): void {
        $user = User::create([
            'name' => 'Fresh User',
            'email' => 'fresh@create-test.com',
            'user_type' => RoleType::User,
            'is_active' => true,
        ]);

        Notification::assertSentTo($user, WelcomeNewUserNotification::class);
    });
});
