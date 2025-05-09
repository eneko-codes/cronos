<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Wireable;

/**
 * Represents overall deviation details comparing different time metrics.
 */
final readonly class OverallDeviationDetails implements Wireable
{
    /**
     * @param  DeviationDetail  $attendanceVsScheduled  Deviation between attendance and scheduled time.
     * @param  DeviationDetail  $workedVsScheduled  Deviation between worked and scheduled time.
     * @param  DeviationDetail  $workedVsAttendance  Deviation between worked time and attendance time.
     */
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
        if (! is_array($value)) {
            throw ValidationException::withMessages(['input' => 'Input data must be an array.']);
        }

        $validator = Validator::make($value, [
            'attendanceVsScheduled' => 'required|array',
            'workedVsScheduled' => 'required|array',
            'workedVsAttendance' => 'required|array',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validatedData = $validator->validated();

        return new self(
            DeviationDetail::fromLivewire($validatedData['attendanceVsScheduled']),
            DeviationDetail::fromLivewire($validatedData['workedVsScheduled']),
            DeviationDetail::fromLivewire($validatedData['workedVsAttendance'])
        );
    }
}
