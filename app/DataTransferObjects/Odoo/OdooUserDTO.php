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
     * @param  array|null  $department_id  Department [id, name] or null
     * @param  array  $category_ids  List of Odoo category IDs assigned to the employee
     * @param  array|null  $resource_calendar_id  Resource calendar [id, name] or null
     * @param  string|null  $job_title  Employee's job title
     * @param  array|null  $parent_id  Manager [id, name] or null
     */
    public function __construct(
        public ?int $id = null,
        public ?string $work_email = null,
        public ?string $name = null,
        public ?string $tz = null,
        public ?bool $active = null,
        public ?array $department_id = null,
        public array $category_ids = [],
        public ?array $resource_calendar_id = null,
        public ?string $job_title = null,
        public ?array $parent_id = null
    ) {}
}
