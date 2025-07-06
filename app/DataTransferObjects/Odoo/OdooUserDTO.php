<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Odoo;

/**
 * Data Transfer Object for Odoo Employee (hr.employee).
 *
 * Represents a user/employee record as returned by the Odoo API.
 */
final readonly class OdooUserDTO
{
    /**
     * @param  int|null  $id  Odoo employee ID
     * @param  string|null  $work_email  Employee's work email address
     * @param  string|null  $name  Employee's full name
     * @param  string|null  $tz  Employee's timezone
     * @param  bool|null  $active  Whether the employee is active
     * @param  int|null  $department_id  Department ID (Odoo)
     * @param  array|null  $category_ids  List of Odoo category IDs assigned to the employee
     * @param  int|null  $resource_calendar_id  Resource calendar ID (Odoo)
     * @param  string|null  $job_title  Employee's job title
     * @param  int|null  $parent_id  Manager's Odoo employee ID
     */
    public function __construct(
        public ?int $id = null,
        public ?string $work_email = null,
        public ?string $name = null,
        public ?string $tz = null,
        public ?bool $active = true,
        public ?int $department_id = null,
        public ?array $category_ids = [],
        public ?int $resource_calendar_id = null,
        public ?string $job_title = null,
        public ?int $parent_id = null
    ) {}
}
