<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Wireable;

/**
 * Represents the data for a single day in a period.
 */
final readonly class PeriodDayData implements Wireable
{
    /**
     * @param  string  $date  The date for this day's data.
     * @param  DailyScheduleData  $scheduled  The scheduled data for the day.
     * @param  DailyLeaveData|null  $leave  The leave data for the day, if any.
     * @param  DailyAttendanceData  $attendance  The attendance data for the day.
     * @param  DailyWorkedData  $worked  The worked time data for the day.
     * @param  DeviationMetrics|null  $deviationDetails  The deviation details for the day, if applicable.
     */
    public function __construct(
        public string $date,
        public DailyScheduleData $scheduled,
        public ?DailyLeaveData $leave,
        public DailyAttendanceData $attendance,
        public DailyWorkedData $worked,
        public ?DeviationMetrics $deviationDetails
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
        if (! is_array($value)) {
            throw ValidationException::withMessages(['input' => 'Input data must be an array.']);
        }

        $validator = Validator::make($value, [
            'date' => 'required|date_format:Y-m-d',
            'scheduled' => 'required|array',
            'leave' => 'nullable|array',
            'attendance' => 'required|array',
            'worked' => 'required|array',
            'deviationDetails' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validatedData = $validator->validated();

        return new self(
            $validatedData['date'],
            DailyScheduleData::fromLivewire($validatedData['scheduled']),
            isset($validatedData['leave']) ? DailyLeaveData::fromLivewire($validatedData['leave']) : null,
            DailyAttendanceData::fromLivewire($validatedData['attendance']),
            DailyWorkedData::fromLivewire($validatedData['worked']),
            isset($validatedData['deviationDetails']) ? DeviationMetrics::fromLivewire($validatedData['deviationDetails']) : null
        );
    }
}
