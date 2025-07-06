<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Odoo;

final readonly class OdooScheduleDetailDTO
{
    public function __construct(
        public ?int $id = null,
        public ?int $calendar_id = null,
        public ?string $name = null,
        public ?int $dayofweek = null,
        public ?float $hour_from = null,
        public ?float $hour_to = null,
        public ?string $day_period = null,
        public ?array $raw = null
    ) {}
}
