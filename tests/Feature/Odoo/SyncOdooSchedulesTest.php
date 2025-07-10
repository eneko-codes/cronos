<?php

declare(strict_types=1);

use App\Clients\OdooApiClient;
use App\DataTransferObjects\Odoo\OdooScheduleDetailDTO;
use App\DataTransferObjects\Odoo\OdooScheduleDTO;
use App\Jobs\Sync\Odoo\SyncOdooSchedules;
use App\Models\Schedule;
use Illuminate\Support\Facades\DB;

describe('SyncOdooSchedules job', function (): void {
    beforeEach(function (): void {
        DB::beginTransaction();
    });
    afterEach(function (): void {
        DB::rollBack();
    });

    it('creates a new schedule and details from OdooScheduleDTO and OdooScheduleDetailDTO', function (): void {
        $scheduleDto = new OdooScheduleDTO(
            id: 1,
            name: 'Standard',
            active: true,
            attendance_ids: [10, 11]
        );
        $detailDto = new OdooScheduleDetailDTO(
            id: 10,
            calendar_id: [1, 'Standard'],
            name: 'Monday Morning',
            dayofweek: 1,
            hour_from: 9.0,
            hour_to: 13.0,
            day_period: 'morning'
        );
        $mockOdoo = Mockery::mock(OdooApiClient::class);
        $mockOdoo->shouldReceive('getSchedules')->once()->andReturn(collect([$scheduleDto]));
        $mockOdoo->shouldReceive('getScheduleDetails')->once()->andReturn(collect([$detailDto]));

        $job = new SyncOdooSchedules($mockOdoo);
        $job->handle();

        $schedule = Schedule::where('odoo_schedule_id', 1)->first();
        expect($schedule)->not()->toBeNull();
        expect($schedule->description)->toBe('Standard');
        expect($schedule->average_hours_day)->toBe(8.0);
        $detail = $schedule->scheduleDetails()->where('odoo_detail_id', 10)->first();
        expect($detail)->not()->toBeNull();
        expect($detail->weekday)->toBe(1);
        expect($detail->day_period)->toBe('morning');
        expect($detail->start->setTimezone('Europe/Madrid')->format('H:i'))->toBe('09:00');
        expect($detail->end->setTimezone('Europe/Madrid')->format('H:i'))->toBe('13:00');
    });

    it('updates an existing schedule from OdooScheduleDTO', function (): void {
        $schedule = Schedule::factory()->create([
            'odoo_schedule_id' => 2,
            'description' => 'Old',
            'average_hours_day' => 7.0,
        ]);
        $scheduleDto = new OdooScheduleDTO(
            id: 2,
            name: 'New',
            active: false,
            attendance_ids: [12, 13]
        );
        $mockOdoo = Mockery::mock(OdooApiClient::class);
        $mockOdoo->shouldReceive('getSchedules')->once()->andReturn(collect([$scheduleDto]));
        $mockOdoo->shouldReceive('getScheduleDetails')->once()->andReturn(collect([]));

        $job = new SyncOdooSchedules($mockOdoo);
        $job->handle();

        $schedule->refresh();
        expect($schedule->description)->toBe('New');
        expect($schedule->average_hours_day)->toBe(8.5);
    });
});
