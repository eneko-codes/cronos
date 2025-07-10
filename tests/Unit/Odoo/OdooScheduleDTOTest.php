<?php

declare(strict_types=1);

use App\DataTransferObjects\Odoo\OdooScheduleDTO;

describe('OdooScheduleDTO', function (): void {
    it('constructs OdooScheduleDTO with correct types and defaults', function (): void {
        $dto = new OdooScheduleDTO(
            id: 50,
            name: 'Standard',
            active: true,
            attendance_ids: [1, 2, 3]
        );
        expect($dto->id)->toBeInt()->toBe(50);
        expect($dto->name)->toBeString()->toBe('Standard');
        expect($dto->active)->toBeTrue();
        expect($dto->attendance_ids)->toBeArray()->toBe([1, 2, 3]);
    });

    it('OdooScheduleDTO can be constructed with all fields null', function (): void {
        $dto = new OdooScheduleDTO;
        expect($dto->id)->toBeNull();
        expect($dto->name)->toBeNull();
        expect($dto->active)->toBeNull();
        expect($dto->attendance_ids)->toBeArray()->toBe([]);
    });
});
