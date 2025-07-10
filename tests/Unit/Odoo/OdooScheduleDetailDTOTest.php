<?php

declare(strict_types=1);

use App\DataTransferObjects\Odoo\OdooScheduleDetailDTO;

describe('OdooScheduleDetailDTO', function (): void {
    it('constructs OdooScheduleDetailDTO with correct types and defaults', function (): void {
        $dto = new OdooScheduleDetailDTO(
            id: 60,
            calendar_id: [61, 'Standard 40h'],
            name: 'Monday Morning',
            dayofweek: 1,
            hour_from: 9.0,
            hour_to: 13.0,
            day_period: 'morning'
        );
        expect($dto->id)->toBeInt()->toBe(60);
        expect($dto->calendar_id)->toBeArray()->toBe([61, 'Standard 40h']);
        expect($dto->name)->toBeString()->toBe('Monday Morning');
        expect($dto->dayofweek)->toBeInt()->toBe(1);
        expect($dto->hour_from)->toBeFloat()->toBe(9.0);
        expect($dto->hour_to)->toBeFloat()->toBe(13.0);
        expect($dto->day_period)->toBeString()->toBe('morning');
    });

    it('OdooScheduleDetailDTO can be constructed with all fields null', function (): void {
        $dto = new App\DataTransferObjects\Odoo\OdooScheduleDetailDTO;
        expect($dto->id)->toBeNull();
        expect($dto->calendar_id)->toBeNull();
        expect($dto->name)->toBeNull();
        expect($dto->dayofweek)->toBeNull();
        expect($dto->hour_from)->toBeNull();
        expect($dto->hour_to)->toBeNull();
        expect($dto->day_period)->toBeNull();
    });
});
