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
     *
     * @property array|null $manager_id Odoo manager employee as [id, name] or null
     * @property array|null $parent_id Odoo parent department as [id, name] or null
     */
    public function __construct(
        public ?int $id = null,
        public ?string $name = null,
        public ?bool $active = null,
        public ?array $manager_id = null,
        public ?array $parent_id = null
    ) {}
}
