<?php

declare(strict_types=1);

describe('OdooScheduleDTO', function (): void {
    it('constructs OdooScheduleDTO with correct types and all fields', function (): void {
        $dto = new App\DataTransferObjects\Odoo\OdooScheduleDTO(
            id: 1,
            description: 'Test Schedule',
            active: true,
            attendance_ids: [1, 2, 3],
            hours_per_day: 8.0,
            two_weeks_calendar: true,
            two_weeks_explanation: 'Odd/Even weeks',
            flexible_hours: false,
            odoo_created_at: '2024-01-01T00:00:00Z',
            odoo_updated_at: '2024-01-02T00:00:00Z',
        );
        expect($dto->id)->toBeInt()->toBe(1);
        expect($dto->description)->toBeString()->toBe('Test Schedule');
        expect($dto->active)->toBeTrue();
        expect($dto->attendance_ids)->toBeArray()->toBe([1, 2, 3]);
        expect($dto->hours_per_day)->toBeFloat()->toBe(8.0);
        expect($dto->two_weeks_calendar)->toBeTrue();
        expect($dto->two_weeks_explanation)->toBeString()->toBe('Odd/Even weeks');
        expect($dto->flexible_hours)->toBeFalse();
        expect($dto->odoo_created_at)->toBeString()->toBe('2024-01-01T00:00:00Z');
        expect($dto->odoo_updated_at)->toBeString()->toBe('2024-01-02T00:00:00Z');
    });

    it('OdooScheduleDTO can be constructed with all fields null', function (): void {
        $dto = new App\DataTransferObjects\Odoo\OdooScheduleDTO;
        expect($dto->id)->toBeNull();
        expect($dto->description)->toBeNull();
        expect($dto->active)->toBeTrue(); // default is true
        expect($dto->attendance_ids)->toBeArray()->toBe([]);
        expect($dto->hours_per_day)->toBeNull();
        expect($dto->two_weeks_calendar)->toBeNull();
        expect($dto->two_weeks_explanation)->toBeNull();
        expect($dto->flexible_hours)->toBeNull();
        expect($dto->odoo_created_at)->toBeNull();
        expect($dto->odoo_updated_at)->toBeNull();
    });
});
