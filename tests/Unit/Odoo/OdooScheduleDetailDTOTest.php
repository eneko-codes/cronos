<?php

declare(strict_types=1);

use App\DataTransferObjects\Odoo\OdooScheduleDetailDTO;

describe('OdooScheduleDetailDTO', function (): void {
    it('constructs OdooScheduleDetailDTO with correct types and defaults', function (): void {
        $dto = new OdooScheduleDetailDTO(
            id: 60,
            calendar_id: 61,
            name: 'Monday Morning',
            dayofweek: 1,
            hour_from: 9.0,
            hour_to: 13.0,
            day_period: 'morning'
        );
        expect($dto->id)->toBeInt()->toBe(60);
        expect($dto->calendar_id)->toBeInt()->toBe(61);
        expect($dto->name)->toBeString()->toBe('Monday Morning');
        expect($dto->dayofweek)->toBeInt()->toBe(1);
        expect($dto->hour_from)->toBeFloat()->toBe(9.0);
        expect($dto->hour_to)->toBeFloat()->toBe(13.0);
        expect($dto->day_period)->toBeString()->toBe('morning');
    });
});
