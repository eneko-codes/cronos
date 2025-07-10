<?php

declare(strict_types=1);

use App\DataTransferObjects\Odoo\OdooCategoryDTO;

describe('OdooCategoryDTO', function (): void {
    it('constructs OdooCategoryDTO with correct types and defaults', function (): void {
        $dto = new OdooCategoryDTO(
            id: 20,
            name: 'Category',
            active: false
        );
        expect($dto->id)->toBeInt()->toBe(20);
        expect($dto->name)->toBeString()->toBe('Category');
        expect($dto->active)->toBeBool()->toBeFalse();
    });

    it('OdooCategoryDTO can be constructed with all fields null', function (): void {
        $dto = new App\DataTransferObjects\Odoo\OdooCategoryDTO;
        expect($dto->id)->toBeNull();
        expect($dto->name)->toBeNull();
        expect($dto->active)->toBeNull();
    });
});
