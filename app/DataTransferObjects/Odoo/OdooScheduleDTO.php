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
     * @param  bool|null  $active  Whether the schedule is active
     * @param  array  $attendance_ids  Array of attendance IDs
     */
    public function __construct(
        public ?int $id = null,
        public ?string $name = null,
        public ?bool $active = null,
        public array $attendance_ids = []
    ) {}
}
