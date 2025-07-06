<?php

declare(strict_types=1);

use App\DataTransferObjects\Odoo\OdooCategoryDTO;
use App\DataTransferObjects\Odoo\OdooDepartmentDTO;
use App\DataTransferObjects\Odoo\OdooLeaveDTO;
use App\DataTransferObjects\Odoo\OdooLeaveTypeDTO;
use App\DataTransferObjects\Odoo\OdooScheduleDetailDTO;
use App\DataTransferObjects\Odoo\OdooScheduleDTO;
use App\DataTransferObjects\Odoo\OdooUserDTO;

describe('OdooDTO', function (): void {
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

    it('constructs OdooLeaveDTO with correct types and defaults', function (): void {
        $dto = new OdooLeaveDTO(
            id: 40,
            holiday_type: 'employee',
            date_from: '2024-01-01 09:00:00',
            date_to: '2024-01-01 18:00:00',
            number_of_days: 1.0,
            state: 'validate',
            holiday_status_id: 41,
            request_hour_from: 9.0,
            request_hour_to: 18.0,
            employee_id: 42,
            category_id: 43,
            department_id: 44
        );
        expect($dto->id)->toBeInt()->toBe(40);
        expect($dto->holiday_type)->toBeString()->toBe('employee');
        expect($dto->date_from)->toBeString()->toBe('2024-01-01 09:00:00');
        expect($dto->date_to)->toBeString()->toBe('2024-01-01 18:00:00');
        expect($dto->number_of_days)->toBeFloat()->toBe(1.0);
        expect($dto->state)->toBeString()->toBe('validate');
        expect($dto->holiday_status_id)->toBeInt()->toBe(41);
        expect($dto->request_hour_from)->toBeFloat()->toBe(9.0);
        expect($dto->request_hour_to)->toBeFloat()->toBe(18.0);
        expect($dto->employee_id)->toBeInt()->toBe(42);
        expect($dto->category_id)->toBeInt()->toBe(43);
        expect($dto->department_id)->toBeInt()->toBe(44);
    });

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

    it('constructs OdooScheduleDetailDTO with correct types and defaults', function (): void {
        $dto = new OdooScheduleDetailDTO(
            id: 60,
            calendar_id: 61,
            name: 'Monday Morning',
            dayofweek: 1,
            hour_from: 9.0,
            hour_to: 13.0,
            day_period: 'morning'
        );
        expect($dto->id)->toBeInt()->toBe(60);
        expect($dto->calendar_id)->toBeInt()->toBe(61);
        expect($dto->name)->toBeString()->toBe('Monday Morning');
        expect($dto->dayofweek)->toBeInt()->toBe(1);
        expect($dto->hour_from)->toBeFloat()->toBe(9.0);
        expect($dto->hour_to)->toBeFloat()->toBe(13.0);
        expect($dto->day_period)->toBeString()->toBe('morning');
    });
});
