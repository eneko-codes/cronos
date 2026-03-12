<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Odoo;

/**
 * Data Transfer Object for Odoo Schedule Detail (resource.calendar.attendance).
 *
 * Represents a schedule detail record as returned by the Odoo API.
 */
final readonly class OdooScheduleDetailDTO
{
    /**
     * @param  int|null  $id  Odoo schedule detail ID
     * @param  array|null  $calendar_id  Odoo calendar as [id, name] or null
     * @param  string|null  $name  Name/label for this schedule detail
     * @param  string|null  $dayofweek  Day of the week (0=Monday, 6=Sunday) as string
     * @param  float|null  $hour_from  Hour the schedule starts
     * @param  float|null  $hour_to  Hour the schedule ends
     * @param  string|null  $day_period  Period of the day (e.g., 'morning', 'afternoon')
     * @param  int|null  $week_type  Determines whether the attendance applies to both weeks (0), week 1 (1), or week 2 (2)
     * @param  string|null  $date_from  Optional start date for when the attendance is active
     * @param  string|null  $date_to  Optional end date for when the attendance is active
     * @param  bool|null  $active  Whether the schedule detail is active (from Odoo)
     * @param  string|null  $create_date  Creation date of the record in Odoo (UTC datetime string).
     * @param  string|null  $write_date  Last write date of the record in Odoo (UTC datetime string).
     */
    public function __construct(
        public ?int $id = null,
        public ?array $calendar_id = null,
        public ?string $name = null,
        public ?string $dayofweek = null,
        public ?float $hour_from = null,
        public ?float $hour_to = null,
        public ?string $day_period = null,
        public ?int $week_type = null,
        public ?string $date_from = null,
        public ?string $date_to = null,
        public ?bool $active = null,
        public ?string $create_date = null,
        public ?string $write_date = null,
    ) {}
}
