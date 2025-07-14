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
     * @param  string|null  $request_unit  Request unit (e.g., 'Day', 'Hour')
     * @param  bool|null  $active  If leave type is active
     * @param  string|null  $create_date  Creation timestamp (UTC)
     * @param  string|null  $write_date  Last modification timestamp (UTC)
     * @param  array|null  $create_uid  User who created the record (Many2one)
     * @param  array|null  $write_uid  Last user to modify (Many2one)
     */
    public function __construct(
        public ?int $id = null,
        public ?string $name = null,
        public ?string $request_unit = null,
        public ?bool $active = null,
        public ?string $create_date = null,
        public ?string $write_date = null,
        public ?array $create_uid = null,
        public ?array $write_uid = null
    ) {}
}
