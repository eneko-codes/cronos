<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Odoo;

final readonly class OdooEmployeeDTO
{
    public function __construct(
        public ?int $id = null,
        public ?string $work_email = null,
        public ?string $name = null,
        public ?string $tz = null,
        public ?bool $active = null,
        public ?array $department_id = null, // [id, name]
        public ?array $category_ids = null, // array of [id, name]
        public ?array $resource_calendar_id = null, // [id, name]
        public ?string $job_title = null,
        public ?array $parent_id = null // [id, name]
    ) {}
}
