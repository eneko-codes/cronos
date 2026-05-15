<?php

declare(strict_types=1);

namespace App\DataTransferObjects\SystemPin;

final readonly class SystemPinAttendanceDTO
{
    public function __construct(
        public ?string $EmployeeID = null,
        public ?int $InternalEmployeeID = null,
        public ?string $Date = null,
        public ?array $TimeRecords = null,
        public ?array $Schedule = null,
        public ?array $TimeOff = null,
        public ?array $TimeOffHours = null,
    ) {}
}
