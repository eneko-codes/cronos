<?php

declare(strict_types=1);

use App\DataTransferObjects\Odoo\OdooLeaveDTO;

describe('OdooLeaveDTO', function (): void {
    it('constructs OdooLeaveDTO with correct types and defaults', function (): void {
        $dto = new OdooLeaveDTO(
            id: 40,
            holiday_type: 'employee',
            date_from: '2024-01-01 09:00:00',
            date_to: '2024-01-01 18:00:00',
            number_of_days: 1.0,
            state: 'validate',
            holiday_status_id: [41, 'Paid Time Off'],
            request_hour_from: 9.0,
            request_hour_to: 18.0,
            employee_id: [42, 'John Doe'],
            category_id: [43, 'Full Time'],
            department_id: [44, 'Engineering']
        );
        expect($dto->id)->toBeInt()->toBe(40);
        expect($dto->holiday_type)->toBeString()->toBe('employee');
        expect($dto->date_from)->toBeString()->toBe('2024-01-01 09:00:00');
        expect($dto->date_to)->toBeString()->toBe('2024-01-01 18:00:00');
        expect($dto->number_of_days)->toBeFloat()->toBe(1.0);
        expect($dto->state)->toBeString()->toBe('validate');
        expect($dto->holiday_status_id)->toBeArray()->toBe([41, 'Paid Time Off']);
        expect($dto->request_hour_from)->toBeFloat()->toBe(9.0);
        expect($dto->request_hour_to)->toBeFloat()->toBe(18.0);
        expect($dto->employee_id)->toBeArray()->toBe([42, 'John Doe']);
        expect($dto->category_id)->toBeArray()->toBe([43, 'Full Time']);
        expect($dto->department_id)->toBeArray()->toBe([44, 'Engineering']);
    });

    it('OdooLeaveDTO can be constructed with all fields null', function (): void {
        $dto = new App\DataTransferObjects\Odoo\OdooLeaveDTO;
        expect($dto->id)->toBeNull();
        expect($dto->holiday_type)->toBeNull();
        expect($dto->date_from)->toBeNull();
        expect($dto->date_to)->toBeNull();
        expect($dto->number_of_days)->toBeNull();
        expect($dto->state)->toBeNull();
        expect($dto->holiday_status_id)->toBeNull();
        expect($dto->request_hour_from)->toBeNull();
        expect($dto->request_hour_to)->toBeNull();
        expect($dto->employee_id)->toBeNull();
        expect($dto->category_id)->toBeNull();
        expect($dto->department_id)->toBeNull();
    });
});
