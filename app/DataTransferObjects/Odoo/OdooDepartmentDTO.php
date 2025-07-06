<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Odoo;

/**
 * Data Transfer Object for Odoo Department (hr.department).
 *
 * Represents a department record as returned by the Odoo API.
 */
final readonly class OdooDepartmentDTO
{
    /**
     * @param  int|null  $id  Odoo department ID
     * @param  string|null  $name  Department name
     * @param  bool|null  $active  Whether the department is active
     * @param  int|null  $manager_id  Odoo manager employee ID
     * @param  int|null  $parent_id  Odoo parent department ID
     */
    public function __construct(
        public ?int $id = null,
        public ?string $name = null,
        public ?bool $active = null,
        public ?int $manager_id = null,
        public ?int $parent_id = null
    ) {}
}
