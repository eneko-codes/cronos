<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Odoo;

final readonly class OdooScheduleDTO
{
    public function __construct(
        public ?int $id = null,
        public ?string $name = null,
        public ?float $hours_per_day = null,
        public ?string $tz = null
    ) {}
}
