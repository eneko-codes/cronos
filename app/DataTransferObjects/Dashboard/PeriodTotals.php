<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Dashboard;

readonly class PeriodTotals
{
    public function __construct(
        public int $scheduled = 0,
        public int $attendance = 0,
        public int $worked = 0,
        public int $leave = 0,
    ) {}

    public function getFormattedScheduled(): string
    {
        return $this->formatMinutes($this->scheduled);
    }

    public function getFormattedAttendance(): string
    {
        return $this->formatMinutes($this->attendance);
    }

    public function getFormattedWorked(): string
    {
        return $this->formatMinutes($this->worked);
    }

    public function getFormattedLeave(): string
    {
        return $this->formatMinutes($this->leave);
    }

    protected function formatMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return '';
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return "{$hours}h {$remainingMinutes}m";
    }
}
