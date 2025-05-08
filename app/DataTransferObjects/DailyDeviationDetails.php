<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Livewire\Wireable;
use Webmozart\Assert\Assert;

final readonly class DailyDeviationDetails implements Wireable
{
    public function __construct(
        public DeviationDetail $attendanceVsScheduled,
        public DeviationDetail $workedVsScheduled,
        public DeviationDetail $workedVsAttendance
    ) {}

    public function toLivewire(): array
    {
        return [
            'attendanceVsScheduled' => $this->attendanceVsScheduled->toLivewire(),
            'workedVsScheduled' => $this->workedVsScheduled->toLivewire(),
            'workedVsAttendance' => $this->workedVsAttendance->toLivewire(),
        ];
    }

    public static function fromLivewire(mixed $value): static
    {
        Assert::isArray($value);
        Assert::keyExists($value, 'attendanceVsScheduled');
        Assert::keyExists($value, 'workedVsScheduled');
        Assert::keyExists($value, 'workedVsAttendance');

        return new self(
            DeviationDetail::fromLivewire($value['attendanceVsScheduled']),
            DeviationDetail::fromLivewire($value['workedVsScheduled']),
            DeviationDetail::fromLivewire($value['workedVsAttendance'])
        );
    }
}
