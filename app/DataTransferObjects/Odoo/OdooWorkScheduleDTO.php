<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Odoo;

final readonly class OdooWorkScheduleDTO
{
    public function __construct(
        public ?int $id = null,
        public ?string $name = null,
        public ?bool $active = true,
        public ?array $attendance_ids = null // array of int
    ) {}
}
