<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Odoo;

/**
 * Data Transfer Object for Odoo Schedule (resource.calendar).
 *
 * Represents a schedule/calendar record as returned by the Odoo API.
 */
final readonly class OdooScheduleDTO
{
    /**
     * @param  int|null  $id  Odoo schedule/calendar ID
     * @param  string|null  $name  Schedule name
     * @param  float|null  $hours_per_day  Number of hours per day
     * @param  string|null  $tz  Timezone for the schedule
     */
    public function __construct(
        public ?int $id = null,
        public ?string $name = null,
        public ?float $hours_per_day = null,
        public ?string $tz = null
    ) {}
}
