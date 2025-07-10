<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Odoo;

/**
 * Data Transfer Object for Odoo Leave (hr.leave).
 *
 * Represents a leave record as returned by the Odoo API.
 */
final readonly class OdooLeaveDTO
{
    /**
     * @param  int|null  $id  Odoo leave ID
     * @param  string|null  $holiday_type  Type of leave (e.g., 'employee', 'category')
     * @param  string|null  $date_from  Start date/time of the leave (UTC)
     * @param  string|null  $date_to  End date/time of the leave (UTC)
     * @param  float|null  $number_of_days  Number of days for the leave
     * @param  string|null  $state  State of the leave (e.g., 'validate', 'draft')
     *
     * @property array|null $holiday_status_id Odoo leave type as [id, name] or null
     *
     * @param  float|null  $request_hour_from  Hour the leave starts (if partial day)
     * @param  float|null  $request_hour_to  Hour the leave ends (if partial day)
     *
     * @property array|null $employee_id Odoo employee as [id, name] or null
     * @property array|null $category_id Odoo category as [id, name] or null
     * @property array|null $department_id Odoo department as [id, name] or null
     */
    public function __construct(
        public ?int $id = null,
        public ?string $holiday_type = null,
        public ?string $date_from = null,
        public ?string $date_to = null,
        public ?float $number_of_days = null,
        public ?string $state = null,
        public ?array $holiday_status_id = null,
        public ?float $request_hour_from = null,
        public ?float $request_hour_to = null,
        public ?array $employee_id = null,
        public ?array $category_id = null,
        public ?array $department_id = null
    ) {}
}
