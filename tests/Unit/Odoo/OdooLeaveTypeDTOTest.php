<?php

declare(strict_types=1);

use App\DataTransferObjects\Odoo\OdooLeaveTypeDTO;

describe('OdooLeaveTypeDTO', function (): void {
    it('constructs OdooLeaveTypeDTO with correct types and defaults', function (): void {
        $dto = new OdooLeaveTypeDTO(
            id: 30,
            name: 'Annual',
            active: true,
            allocation_type: 'fixed',
            validation_type: 'manager',
            request_unit: 'day',
            unpaid: false
        );
        expect($dto->id)->toBeInt()->toBe(30);
        expect($dto->name)->toBeString()->toBe('Annual');
        expect($dto->active)->toBeBool()->toBeTrue();
        expect($dto->allocation_type)->toBeString()->toBe('fixed');
        expect($dto->validation_type)->toBeString()->toBe('manager');
        expect($dto->request_unit)->toBeString()->toBe('day');
        expect($dto->unpaid)->toBeBool()->toBeFalse();
    });

    it('OdooLeaveTypeDTO can be constructed with all fields null', function (): void {
        $dto = new App\DataTransferObjects\Odoo\OdooLeaveTypeDTO;
        expect($dto->id)->toBeNull();
        expect($dto->name)->toBeNull();
        expect($dto->active)->toBeNull();
        expect($dto->allocation_type)->toBeNull();
        expect($dto->validation_type)->toBeNull();
        expect($dto->request_unit)->toBeNull();
        expect($dto->unpaid)->toBeNull();
    });
});
