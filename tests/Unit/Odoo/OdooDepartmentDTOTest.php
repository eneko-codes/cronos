<?php

declare(strict_types=1);

use App\DataTransferObjects\Odoo\OdooDepartmentDTO;

describe('OdooDepartmentDTO', function (): void {
    it('constructs OdooDepartmentDTO with correct types and defaults', function (): void {
        $dto = new OdooDepartmentDTO(
            id: 10,
            name: 'HR',
            active: true,
            manager_id: [11, 'Jane Manager'],
            parent_id: [12, 'Company']
        );
        expect($dto->id)->toBeInt()->toBe(10);
        expect($dto->name)->toBeString()->toBe('HR');
        expect($dto->active)->toBeBool()->toBeTrue();
        expect($dto->manager_id)->toBeArray()->toBe([11, 'Jane Manager']);
        expect($dto->parent_id)->toBeArray()->toBe([12, 'Company']);
    });

    it('OdooDepartmentDTO can be constructed with all fields null', function (): void {
        $dto = new App\DataTransferObjects\Odoo\OdooDepartmentDTO;
        expect($dto->id)->toBeNull();
        expect($dto->name)->toBeNull();
        expect($dto->active)->toBeNull();
        expect($dto->manager_id)->toBeNull();
        expect($dto->parent_id)->toBeNull();
    });
});
