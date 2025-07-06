<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Odoo;

final readonly class OdooLeaveTypeDTO
{
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
