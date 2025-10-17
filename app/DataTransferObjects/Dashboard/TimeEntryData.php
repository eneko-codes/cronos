<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Dashboard;

readonly class TimeEntryData
{
    public function __construct(
        public ?string $duration = null,
        public array $projects = [],
        public array $detailedEntries = [],
    ) {}

    public function isEmpty(): bool
    {
        return empty($this->duration) || $this->duration === '0h 0m';
    }

    public function hasData(): bool
    {
        return !$this->isEmpty();
    }
}
