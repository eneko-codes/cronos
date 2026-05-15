<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Desktime;

final readonly class DesktimeAttendanceDTO
{
    /**
     * @param  string|null  $date  The specific date for this attendance record.
     */
    public function __construct(
        public ?int $id = null,
        public ?string $name = null,
        public ?string $email = null,
        public ?int $groupId = null,
        public ?string $group = null,
        public ?string $profileUrl = null,
        public ?bool $isOnline = null,
        public string|bool|null $arrived = null,
        public string|bool|null $left = null,
        public ?bool $late = null,
        public ?int $onlineTime = null,
        public ?int $offlineTime = null,
        public ?int $desktimeTime = null,
        public ?int $atWorkTime = null,
        public ?int $afterWorkTime = null,
        public ?int $beforeWorkTime = null,
        public ?int $productiveTime = null,
        public ?float $productivity = null,
        public ?float $efficiency = null,
        public string|bool|null $work_starts = null,
        public string|bool|null $work_ends = null,
        public ?array $notes = null,
        public array|object|null $activeProject = null,
        public array|object|null $apps = null,
        public ?array $projects = null,
        public ?string $date = null,
    ) {}
}
