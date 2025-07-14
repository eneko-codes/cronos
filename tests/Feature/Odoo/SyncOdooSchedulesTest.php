<?php

declare(strict_types=1);

use App\DataTransferObjects\Odoo\OdooScheduleDetailDTO;
use App\DataTransferObjects\Odoo\OdooScheduleDTO;
use App\Jobs\Sync\Odoo\SyncOdooScheduleDetailsJob;
use App\Jobs\Sync\Odoo\SyncOdooSchedulesJob;
use App\Models\Schedule;
use App\Models\ScheduleDetail;
use Illuminate\Support\Facades\DB;

// Professional, robust feature test for Odoo schedule sync

describe('Odoo Schedule Sync Jobs', function (): void {
    beforeEach(function (): void {
        DB::beginTransaction();
    });
    afterEach(function (): void {
        DB::rollBack();
    });

    it('creates a new schedule and its details from Odoo DTOs', function (): void {
        // Arrange
        $scheduleDto = new OdooScheduleDTO(
            id: 100,
            description: 'Pro Test Schedule',
            active: true,
            attendance_ids: [200, 201],
            hours_per_day: 8.5,
            two_weeks_calendar: false,
            two_weeks_explanation: null,
            flexible_hours: false,
            odoo_created_at: '2024-01-01T08:00:00Z',
            odoo_updated_at: '2024-01-02T08:00:00Z',
        );
        $detailDto = new OdooScheduleDetailDTO(
            id: 200,
            calendar_id: [100, 'Pro Test Schedule'],
            name: 'Monday Morning',
            dayofweek: '0',
            hour_from: 9.0,
            hour_to: 13.0,
            day_period: 'morning',
            week_type: 0,
            date_from: '2024-01-01',
            date_to: '2024-06-30',
            active: true,
            create_date: '2024-01-01T08:00:00Z',
            write_date: '2024-01-02T08:00:00Z',
        );

        // Act
        $scheduleJob = new SyncOdooSchedulesJob(collect([$scheduleDto]));
        $scheduleJob->handle();
        $detailJob = new SyncOdooScheduleDetailsJob(collect([$detailDto]));
        $detailJob->handle();

        // Assert
        $schedule = Schedule::where('odoo_schedule_id', 100)->first();
        expect($schedule)->not()->toBeNull();
        expect($schedule->description)->toBe('Pro Test Schedule');
        expect($schedule->hours_per_day)->toBe(8.5);
        expect($schedule->active)->toBeTrue();
        $detail = ScheduleDetail::where('odoo_detail_id', 200)->first();
        expect($detail)->not()->toBeNull();
        expect($detail->weekday)->toBe(0);
        expect($detail->day_period)->toBe('morning');
        expect(\Illuminate\Support\Carbon::parse($detail->start)->format('H:i'))->toBe('09:00');
        expect(\Illuminate\Support\Carbon::parse($detail->end)->format('H:i'))->toBe('13:00');
        expect($detail->week_type)->toBe(0);
        expect($detail->date_from)->toBe('2024-01-01');
        expect($detail->date_to)->toBe('2024-06-30');
        expect($detail->active)->toBeTrue();
    });

    it('updates an existing schedule and its details from Odoo DTOs', function (): void {
        // Arrange
        $schedule = Schedule::factory()->create([
            'odoo_schedule_id' => 101,
            'description' => 'Old Schedule',
            'hours_per_day' => 7.0,
            'active' => true,
        ]);
        $detail = ScheduleDetail::factory()->create([
            'odoo_detail_id' => 201,
            'odoo_schedule_id' => 101,
            'weekday' => 1,
            'day_period' => 'afternoon',
            'start' => '14:00:00',
            'end' => '18:00:00',
            'active' => true,
        ]);
        $scheduleDto = new OdooScheduleDTO(
            id: 101,
            description: 'Updated Pro Schedule',
            active: false,
            attendance_ids: [201],
            hours_per_day: 7.5,
            two_weeks_calendar: true,
            two_weeks_explanation: 'Bi-weekly',
            flexible_hours: true,
            odoo_created_at: '2024-02-01T08:00:00Z',
            odoo_updated_at: '2024-02-02T08:00:00Z',
        );
        $detailDto = new OdooScheduleDetailDTO(
            id: 201,
            calendar_id: [101, 'Updated Pro Schedule'],
            name: 'Friday Afternoon',
            dayofweek: '4',
            hour_from: 15.0,
            hour_to: 19.0,
            day_period: 'afternoon',
            week_type: 2,
            date_from: '2024-02-01',
            date_to: '2024-07-31',
            active: false,
            create_date: '2024-02-01T08:00:00Z',
            write_date: '2024-02-02T08:00:00Z',
        );

        // Act
        $scheduleJob = new SyncOdooSchedulesJob(collect([$scheduleDto]));
        $scheduleJob->handle();
        $detailJob = new SyncOdooScheduleDetailsJob(collect([$detailDto]));
        $detailJob->handle();

        // Assert
        $schedule->refresh();
        expect($schedule->description)->toBe('Updated Pro Schedule');
        expect($schedule->hours_per_day)->toBe(7.5);
        expect($schedule->active)->toBeFalse();
        $detail->refresh();
        expect($detail->weekday)->toBe(4);
        expect($detail->day_period)->toBe('afternoon');
        expect(\Illuminate\Support\Carbon::parse($detail->start)->format('H:i'))->toBe('15:00');
        expect(\Illuminate\Support\Carbon::parse($detail->end)->format('H:i'))->toBe('19:00');
        expect($detail->week_type)->toBe(2);
        expect($detail->date_from)->toBe('2024-02-01');
        expect($detail->date_to)->toBe('2024-07-31');
        expect($detail->active)->toBeFalse();
    });
});
