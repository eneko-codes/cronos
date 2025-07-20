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
     * @param  string|null  $name  Schedule name (from Odoo 'name' field)
     * @param  bool|null  $active  Whether the schedule is active
     * @param  array  $attendance_ids  Array of attendance IDs (raw from Odoo)
     * @param  float|null  $hours_per_day  Average working hours per day (from Odoo)
     * @param  bool|null  $two_weeks_calendar  Indicates if the calendar has a bi-weekly rotation
     * @param  string|null  $two_weeks_explanation  Human-readable explanation of the two-week rotation
     * @param  bool|null  $flexible_hours  Whether the calendar allows flexible start/end times
     * @param  string|null  $create_date  Creation date of the record in Odoo (UTC datetime string).
     * @param  string|null  $write_date  Last write date of the record in Odoo (UTC datetime string).
     */
    public function __construct(
        public ?int $id = null,
        public ?string $name = null,
        public ?bool $active = null,
        public array $attendance_ids = [],
        public ?float $hours_per_day = null,
        public ?bool $two_weeks_calendar = null,
        public ?string $two_weeks_explanation = null,
        public ?bool $flexible_hours = null,
        public ?string $create_date = null,
        public ?string $write_date = null,
    ) {}
}
