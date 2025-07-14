<?php

declare(strict_types=1);

describe('OdooScheduleDetailDTO', function (): void {
    it('constructs OdooScheduleDetailDTO with correct types and all fields', function (): void {
        $dto = new App\DataTransferObjects\Odoo\OdooScheduleDetailDTO(
            id: 60,
            calendar_id: [61, 'Standard 40h'],
            name: 'Monday Morning',
            dayofweek: '1',
            hour_from: 9.0,
            hour_to: 13.0,
            day_period: 'morning',
            week_type: 0,
            date_from: '2024-01-01',
            date_to: '2024-06-30',
            active: true,
            create_date: '2024-01-01T09:00:00Z',
            write_date: '2024-01-10T09:00:00Z',
        );
        expect($dto->id)->toBeInt()->toBe(60);
        expect($dto->calendar_id)->toBeArray()->toBe([61, 'Standard 40h']);
        expect($dto->name)->toBeString()->toBe('Monday Morning');
        expect($dto->dayofweek)->toBeString()->toBe('1');
        expect($dto->hour_from)->toBeFloat()->toBe(9.0);
        expect($dto->hour_to)->toBeFloat()->toBe(13.0);
        expect($dto->day_period)->toBeString()->toBe('morning');
        expect($dto->week_type)->toBeInt()->toBe(0);
        expect($dto->date_from)->toBeString()->toBe('2024-01-01');
        expect($dto->date_to)->toBeString()->toBe('2024-06-30');
        expect($dto->active)->toBeTrue();
        expect($dto->create_date)->toBeString()->toBe('2024-01-01T09:00:00Z');
        expect($dto->write_date)->toBeString()->toBe('2024-01-10T09:00:00Z');
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
        expect($dto->week_type)->toBeNull();
        expect($dto->date_from)->toBeNull();
        expect($dto->date_to)->toBeNull();
        expect($dto->active)->toBeNull();
        expect($dto->create_date)->toBeNull();
        expect($dto->write_date)->toBeNull();
    });
});
