<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Odoo;

/**
 * Data Transfer Object for Odoo Leave Type (hr.leave.type).
 *
 * Represents a leave type record as returned by the Odoo API.
 */
final readonly class OdooLeaveTypeDTO
{
    /**
     * @param  int|null  $id  Odoo leave type ID
     * @param  string|null  $name  Leave type name
     * @param  bool|null  $active  Whether the leave type is active
     * @param  string|null  $allocation_type  Allocation type (e.g., 'fixed', 'no')
     * @param  string|null  $validation_type  Validation type (e.g., 'manager', 'hr')
     * @param  string|null  $request_unit  Request unit (e.g., 'day', 'hour')
     * @param  bool|null  $unpaid  Whether the leave type is unpaid
     */
    public function __construct(
        public ?int $id = null,
        public ?string $name = null,
        public ?bool $active = true,
        public ?string $allocation_type = null,
        public ?string $validation_type = null,
        public ?string $request_unit = null,
        public ?bool $unpaid = null
    ) {}
}
