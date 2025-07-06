<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Odoo;

final readonly class OdooLeaveDTO
{
    public function __construct(
        public ?int $id = null,
        public ?string $holiday_type = null,
        public ?string $date_from = null,
        public ?string $date_to = null,
        public ?float $number_of_days = null,
        public ?string $state = null,
        public ?int $holiday_status_id = null,
        public ?float $request_hour_from = null,
        public ?float $request_hour_to = null,
        public ?int $employee_id = null,
        public ?int $category_id = null,
        public ?int $department_id = null
    ) {}
}
