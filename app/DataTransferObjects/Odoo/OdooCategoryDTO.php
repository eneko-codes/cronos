<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Odoo;

/**
 * Data Transfer Object for Odoo Employee Category (hr.employee.category).
 *
 * Represents a category record as returned by the Odoo API.
 */
final readonly class OdooCategoryDTO
{
    /**
     * @param  int|null  $id  Odoo category ID
     * @param  string|null  $name  Category name
     * @param  bool|null  $active  Whether the category is active
     */
    public function __construct(
        public ?int $id = null,
        public ?string $name = null,
        public ?bool $active = null
    ) {}
}
