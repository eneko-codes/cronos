<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Dashboard;

readonly class DayData
{
    public function __construct(
        public string $date,
        public ?ScheduleData $schedule = null,
        public ?LeaveData $leave = null,
        public ?AttendanceData $attendance = null,
        public ?TimeEntryData $worked = null,
        public ?DeviationData $attendanceVsScheduled = null,
        public ?DeviationData $workedVsScheduled = null,
        public ?DeviationData $workedVsAttendance = null,
    ) {}

    public function isFuture(): bool
    {
        return \Carbon\Carbon::parse($this->date)->isFuture();
    }

    public function isToday(): bool
    {
        return \Carbon\Carbon::parse($this->date)->isToday();
    }

    public function isWeekend(): bool
    {
        return \Carbon\Carbon::parse($this->date)->isWeekend();
    }

    public function isPastOrToday(): bool
    {
        return !$this->isFuture();
    }
}
