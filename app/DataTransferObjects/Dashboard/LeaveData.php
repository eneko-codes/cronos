<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Dashboard;

readonly class LeaveData
{
    public function __construct(
        public ?string $duration = null,
        public ?string $durationText = null,
        public float $durationDays = 0.0,
        public string $status = 'validate',
        public bool $isHalfDay = false,
        public string $timePeriod = 'full-day',
        public ?string $timeRange = null,
        public ?string $halfDayTime = null,
        public ?string $startTime = null,
        public ?string $endTime = null,
        public int $actualMinutes = 0,
        public ?string $leaveType = null,
        public ?string $context = null,
    ) {}

    public function isEmpty(): bool
    {
        return empty($this->duration) || $this->duration === '0h 0m';
    }

    public function hasData(): bool
    {
        return ! $this->isEmpty();
    }

    public function isApproved(): bool
    {
        return $this->status === 'validate';
    }

    public function isPending(): bool
    {
        return $this->status === 'confirm';
    }

    public function isCancelled(): bool
    {
        return in_array($this->status, ['cancel', 'refuse']);
    }
}
