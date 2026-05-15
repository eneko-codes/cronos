<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Dashboard;

readonly class AttendanceData
{
    public function __construct(
        public ?string $duration = null,
        public bool $isRemote = false,
        public bool $isMixed = false,
        public bool $hasOffice = false,
        public bool $hasRemote = false,
        public array $segments = [],
        public bool $hasOpenSegment = false,
        public ?string $start = null,
        public ?string $end = null,
    ) {}

    public function isEmpty(): bool
    {
        return empty($this->duration) || $this->duration === '0h 0m';
    }

    public function hasData(): bool
    {
        return ! $this->isEmpty();
    }
}
