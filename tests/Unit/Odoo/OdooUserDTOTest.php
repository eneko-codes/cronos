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
            department_id: [2, 'Engineering'],
            category_ids: [[3, 'Full Time'], [4, 'Remote']],
            resource_calendar_id: [5, 'Standard 40h'],
            job_title: 'Engineer',
            parent_id: [6, 'Jane Manager']
        );
        expect($dto->id)->toBeInt()->toBe(1);
        expect($dto->work_email)->toBeString()->toBe('user@example.com');
        expect($dto->name)->toBeString()->toBe('Test User');
        expect($dto->tz)->toBeString()->toBe('Europe/Madrid');
        expect($dto->active)->toBeBool()->toBeFalse();
        expect($dto->department_id)->toBeArray()->toBe([2, 'Engineering']);
        expect($dto->category_ids)->toBeArray()->toBe([[3, 'Full Time'], [4, 'Remote']]);
        expect($dto->resource_calendar_id)->toBeArray()->toBe([5, 'Standard 40h']);
        expect($dto->job_title)->toBeString()->toBe('Engineer');
        expect($dto->parent_id)->toBeArray()->toBe([6, 'Jane Manager']);
    });
});
