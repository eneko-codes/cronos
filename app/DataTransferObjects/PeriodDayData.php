<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Livewire\Wireable;
use Webmozart\Assert\Assert;

final readonly class PeriodDayData implements Wireable
{
    public function __construct(
        public string $date,
        public DailyScheduleData $scheduled,
        public ?DailyLeaveData $leave,
        public DailyAttendanceData $attendance,
        public DailyWorkedData $worked,
        public ?DailyDeviationDetails $deviationDetails
    ) {}

    public function toLivewire(): array
    {
        return [
            'date' => $this->date,
            'scheduled' => $this->scheduled->toLivewire(),
            'leave' => $this->leave?->toLivewire(),
            'attendance' => $this->attendance->toLivewire(),
            'worked' => $this->worked->toLivewire(),
            'deviationDetails' => $this->deviationDetails?->toLivewire(),
        ];
    }

    public static function fromLivewire(mixed $value): static
    {
        Assert::isArray($value);
        Assert::keyExists($value, 'date');
        // Add other assertions as needed

        return new self(
            $value['date'],
            DailyScheduleData::fromLivewire($value['scheduled']),
            isset($value['leave']) ? DailyLeaveData::fromLivewire($value['leave']) : null,
            DailyAttendanceData::fromLivewire($value['attendance']),
            DailyWorkedData::fromLivewire($value['worked']),
            isset($value['deviationDetails']) ? DailyDeviationDetails::fromLivewire($value['deviationDetails']) : null
        );
    }
}
