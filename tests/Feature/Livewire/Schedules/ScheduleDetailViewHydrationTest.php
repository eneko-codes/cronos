<?php

declare(strict_types=1);

use App\Livewire\Schedules\ScheduleDetailView;
use App\Models\Schedule;
use App\Models\ScheduleDetail;
use Illuminate\Database\Eloquent\Model;

beforeEach(function (): void {
    Model::unguard();
});

afterEach(function (): void {
    Model::reguard();
});

/**
 * Regression tests for the Livewire hydration bug in ScheduleDetailView (audit critical #4).
 *
 * Same root cause as the fixed ProjectDetailView bug: strict mode + Livewire's default
 * hydrator strips relations. The component reads $this->schedule->scheduleDetails in
 * groupedScheduleDetails() and would throw LazyLoadingViolation on every Livewire
 * roundtrip without the hydrate() fix.
 */
describe('ScheduleDetailView::hydrate', function (): void {
    it('restores scheduleDetails relation after a simulated hydration', function (): void {
        $schedule = Schedule::factory()->create();
        ScheduleDetail::factory()->count(5)->create([
            'odoo_schedule_id' => $schedule->odoo_schedule_id,
        ]);

        $component = new ScheduleDetailView;
        $component->mount($schedule);

        // Simulate Livewire hydration: refetch the schedule with no relations loaded.
        $component->schedule = Schedule::find($schedule->odoo_schedule_id);
        expect($component->schedule->relationLoaded('scheduleDetails'))->toBeFalse();

        $component->hydrate();

        expect($component->schedule->relationLoaded('scheduleDetails'))->toBeTrue()
            ->and($component->schedule->scheduleDetails)->toHaveCount(5);
    });

    it('groupedScheduleDetails works after a hydration cycle', function (): void {
        $schedule = Schedule::factory()->create();
        // Two slots on Monday + one on Wednesday — exercises sortBy + groupBy paths.
        ScheduleDetail::factory()->create([
            'odoo_schedule_id' => $schedule->odoo_schedule_id,
            'weekday' => 1,
            'start' => '2024-01-01 09:00:00',
            'end' => '2024-01-01 13:00:00',
        ]);
        ScheduleDetail::factory()->create([
            'odoo_schedule_id' => $schedule->odoo_schedule_id,
            'weekday' => 1,
            'start' => '2024-01-01 14:00:00',
            'end' => '2024-01-01 18:00:00',
        ]);
        ScheduleDetail::factory()->create([
            'odoo_schedule_id' => $schedule->odoo_schedule_id,
            'weekday' => 3,
            'start' => '2024-01-01 09:00:00',
            'end' => '2024-01-01 17:00:00',
        ]);

        $component = new ScheduleDetailView;
        $component->mount($schedule);

        // Wipe relations to mimic Livewire's post-hydration state
        $component->schedule = Schedule::find($schedule->odoo_schedule_id);
        $component->hydrate();

        $grouped = $component->groupedScheduleDetails();

        expect($grouped->keys()->all())->toEqual([1, 3])
            ->and($grouped->get(1))->toHaveCount(2)
            ->and($grouped->get(3))->toHaveCount(1);
    });
});
