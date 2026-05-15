<?php

declare(strict_types=1);

use App\Enums\RoleType;
use App\Livewire\Projects\ProjectDetailView;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

beforeEach(function (): void {
    Model::unguard();
});

afterEach(function (): void {
    Model::reguard();
});

/**
 * Regression tests for the Livewire hydration bug in ProjectDetailView.
 *
 * Bug context: Model::shouldBeStrict() enables both preventLazyLoading and
 * preventAccessingMissingAttributes. Livewire's default hydration re-fetches
 * Eloquent models WITHOUT their preloaded relations or withCount() extras.
 * The fix in ProjectDetailView (see hydrate() + refreshProjectData() + the
 * cached $taskTimeEntryCounts plain array) re-loads relations on every request.
 *
 * NOTE: Livewire's test harness doesn't trigger the hydrate() lifecycle hook
 * for #[Lazy] components, so the direct-invocation pattern below exercises the
 * same code paths that production hits when a real Livewire roundtrip occurs.
 */
describe('ProjectDetailView::refreshProjectData', function (): void {
    it('populates withCount results into the cached array on mount', function (): void {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@hydration-test.com',
            'user_type' => RoleType::Admin,
            'is_active' => true,
        ]);
        $project = Project::factory()->create();
        $task = Task::factory()->create(['proofhub_project_id' => $project->proofhub_project_id]);
        TimeEntry::factory()->count(3)->create([
            'proofhub_project_id' => $project->proofhub_project_id,
            'proofhub_task_id' => $task->proofhub_task_id,
            'user_id' => $admin->id,
        ]);

        $component = new ProjectDetailView;
        $component->mount($project);

        expect($component->taskTimeEntryCounts)->toBeArray()
            ->and($component->taskTimeEntryCounts[$task->proofhub_task_id])->toBe(3);
    });

    it('restores task.users relation when called after a simulated hydration', function (): void {
        $assignee = User::create([
            'name' => 'Assignee',
            'email' => 'assignee@hydration-test.com',
            'user_type' => RoleType::User,
            'is_active' => true,
        ]);
        $project = Project::factory()->create();
        $task = Task::factory()->create(['proofhub_project_id' => $project->proofhub_project_id]);
        $task->users()->attach($assignee->id);

        $component = new ProjectDetailView;
        $component->mount($project);

        // Simulate a Livewire hydration: re-fetch project from DB so all eager-loaded
        // relations are GONE (this is exactly what Livewire's default hydrator does).
        $component->project = Project::find($project->proofhub_project_id);

        // Without the hydrate() fix, accessing $task->users below would throw
        // LazyLoadingViolationException because strict mode is on. With the fix,
        // hydrate() calls refreshProjectData() which re-eager-loads everything.
        $component->hydrate();

        expect($component->tasks)->not->toBeNull()
            ->and($component->tasks->first()->relationLoaded('users'))->toBeTrue()
            ->and($component->tasks->first()->users->first()->id)->toBe($assignee->id);
    });

    it('restores withCount on tasks after a simulated hydration', function (): void {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin3@hydration-test.com',
            'user_type' => RoleType::Admin,
            'is_active' => true,
        ]);
        $project = Project::factory()->create();
        $task = Task::factory()->create(['proofhub_project_id' => $project->proofhub_project_id]);
        TimeEntry::factory()->count(5)->create([
            'proofhub_project_id' => $project->proofhub_project_id,
            'proofhub_task_id' => $task->proofhub_task_id,
            'user_id' => $admin->id,
        ]);

        $component = new ProjectDetailView;
        $component->mount($project);
        $component->project = Project::find($project->proofhub_project_id); // strip relations
        $component->hydrate();

        // The cached array survives hydration AND gets recomputed each time
        expect($component->taskTimeEntryCounts[$task->proofhub_task_id])->toBe(5);
    });
});
