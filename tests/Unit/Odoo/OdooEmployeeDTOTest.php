<?php

declare(strict_types=1);

use App\DataTransferObjects\Odoo\OdooEmployeeDTO;

describe('OdooEmployeeDTO', function (): void {
    it('constructs OdooEmployeeDTO with correct types and defaults', function (): void {
        $dto = new OdooEmployeeDTO(
            id: 1,
            work_email: 'john.doe@company.com',
            name: 'John Doe',
            tz: 'Europe/Madrid',
            active: true,
            department_id: [2, 'Engineering'],
            category_ids: [[1, 'Full Time'], [2, 'Remote']],
            resource_calendar_id: [1, 'Standard 40h'],
            job_title: 'Developer',
            parent_id: [3, 'Jane Manager']
        );
        expect($dto->id)->toBeInt()->toBe(1);
        expect($dto->work_email)->toBeString()->toBe('john.doe@company.com');
        expect($dto->name)->toBeString()->toBe('John Doe');
        expect($dto->tz)->toBeString()->toBe('Europe/Madrid');
        expect($dto->active)->toBeBool()->toBeTrue();
        expect($dto->department_id)->toBeArray()->toBe([2, 'Engineering']);
        expect($dto->category_ids)->toBeArray()->toBe([[1, 'Full Time'], [2, 'Remote']]);
        expect($dto->resource_calendar_id)->toBeArray()->toBe([1, 'Standard 40h']);
        expect($dto->job_title)->toBeString()->toBe('Developer');
        expect($dto->parent_id)->toBeArray()->toBe([3, 'Jane Manager']);
    });

    it('OdooEmployeeDTO can be constructed with all fields null', function (): void {
        $dto = new App\DataTransferObjects\Odoo\OdooEmployeeDTO;
        expect($dto->id)->toBeNull();
        expect($dto->work_email)->toBeNull();
        expect($dto->name)->toBeNull();
        expect($dto->tz)->toBeNull();
        expect($dto->active)->toBeNull();
        expect($dto->department_id)->toBeNull();
        expect($dto->category_ids)->toBeNull();
        expect($dto->resource_calendar_id)->toBeNull();
        expect($dto->job_title)->toBeNull();
        expect($dto->parent_id)->toBeNull();
    });
});
