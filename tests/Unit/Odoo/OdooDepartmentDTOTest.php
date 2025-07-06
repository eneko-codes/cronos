<?php

declare(strict_types=1);

use App\DataTransferObjects\Odoo\OdooDepartmentDTO;

describe('OdooDepartmentDTO', function (): void {
    it('constructs OdooDepartmentDTO with correct types and defaults', function (): void {
        $dto = new OdooDepartmentDTO(
            id: 10,
            name: 'HR',
            active: true,
            manager_id: 11,
            parent_id: 12
        );
        expect($dto->id)->toBeInt()->toBe(10);
        expect($dto->name)->toBeString()->toBe('HR');
        expect($dto->active)->toBeBool()->toBeTrue();
        expect($dto->manager_id)->toBeInt()->toBe(11);
        expect($dto->parent_id)->toBeInt()->toBe(12);
    });
});
