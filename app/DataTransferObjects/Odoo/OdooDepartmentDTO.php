<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Odoo;

final readonly class OdooDepartmentDTO
{
    public function __construct(
        public ?int $id = null,
        public ?string $name = null,
        public ?bool $active = null,
        public ?int $manager_id = null,
        public ?int $parent_id = null
    ) {}
}
