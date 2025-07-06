<?php

declare(strict_types=1);

use App\DataTransferObjects\Odoo\OdooScheduleDTO;

describe('OdooScheduleDTO', function (): void {
    it('constructs OdooScheduleDTO with correct types and defaults', function (): void {
        $dto = new OdooScheduleDTO(
            id: 50,
            name: 'Standard',
            hours_per_day: 8.0,
            tz: 'Europe/Madrid'
        );
        expect($dto->id)->toBeInt()->toBe(50);
        expect($dto->name)->toBeString()->toBe('Standard');
        expect($dto->hours_per_day)->toBeFloat()->toBe(8.0);
        expect($dto->tz)->toBeString()->toBe('Europe/Madrid');
    });
});
