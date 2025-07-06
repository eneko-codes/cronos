<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Odoo;

final readonly class OdooUserDTO
{
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
