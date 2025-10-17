<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Dashboard;

readonly class ScheduleData
{
    public function __construct(
        public ?string $duration = null,
        public array $slots = [],
        public ?string $scheduleName = null,
        public int $totalMinutes = 0,
    ) {}

    public function isEmpty(): bool
    {
        return $this->totalMinutes === 0;
    }

    public function hasData(): bool
    {
        return !$this->isEmpty();
    }
}
