<?php

declare(strict_types=1);

use App\DataTransferObjects\Odoo\OdooUserDTO;

describe('OdooUserDTO', function (): void {
    it('constructs OdooUserDTO with correct types and defaults', function (): void {
        $dto = new OdooUserDTO(
            id: 1,
            work_email: 'user@example.com',
            name: 'Test User',
            tz: 'Europe/Madrid',
            active: false,
            department_id: 2,
            category_ids: [3, 4],
            resource_calendar_id: 5,
            job_title: 'Engineer',
            parent_id: 6
        );
        expect($dto->id)->toBeInt()->toBe(1);
        expect($dto->work_email)->toBeString()->toBe('user@example.com');
        expect($dto->name)->toBeString()->toBe('Test User');
        expect($dto->tz)->toBeString()->toBe('Europe/Madrid');
        expect($dto->active)->toBeBool()->toBeFalse();
        expect($dto->department_id)->toBeInt()->toBe(2);
        expect($dto->category_ids)->toBeArray()->toBe([3, 4]);
        expect($dto->resource_calendar_id)->toBeInt()->toBe(5);
        expect($dto->job_title)->toBeString()->toBe('Engineer');
        expect($dto->parent_id)->toBeInt()->toBe(6);
    });
});
