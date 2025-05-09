<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Wireable;

/**
 * Represents daily leave data.
 */
final readonly class DailyLeaveData implements Wireable
{
    /**
     * @param  string  $type  The type of leave.
     * @param  string  $context  The context of the leave.
     * @param  string  $leaveType  The specific leave type.
     * @param  string  $duration  The duration of the leave in a human-readable format.
     * @param  string  $durationHours  The duration of the leave in hours.
     * @param  float  $durationDays  The duration of the leave in days.
     * @param  string  $status  The status of the leave request.
     * @param  bool  $isHalfDay  Whether the leave is for a half day.
     * @param  string  $timePeriod  The time period of the leave (e.g., morning, afternoon).
     * @param  string  $timeRange  The time range of the leave.
     * @param  string|null  $halfDayTime  The specific time for a half-day leave.
     * @param  string  $startTime  The start time of the leave.
     * @param  string  $endTime  The end time of the leave.
     * @param  int  $actualMinutes  The actual duration of the leave in minutes.
     * @param  string|null  $leaveTypeDescription  A description of the leave type.
     */
    public function __construct(
        public string $type,
        public string $context,
        public string $leaveType,
        public string $duration,
        public string $durationHours,
        public float $durationDays,
        public string $status,
        public bool $isHalfDay,
        public string $timePeriod,
        public string $timeRange,
        public ?string $halfDayTime,
        public string $startTime,
        public string $endTime,
        public int $actualMinutes,
        public ?string $leaveTypeDescription
    ) {}

    public function toLivewire(): array
    {
        return [
            'type' => $this->type,
            'context' => $this->context,
            'leaveType' => $this->leaveType,
            'duration' => $this->duration,
            'durationHours' => $this->durationHours,
            'durationDays' => $this->durationDays,
            'status' => $this->status,
            'isHalfDay' => $this->isHalfDay,
            'timePeriod' => $this->timePeriod,
            'timeRange' => $this->timeRange,
            'halfDayTime' => $this->halfDayTime,
            'startTime' => $this->startTime,
            'endTime' => $this->endTime,
            'actualMinutes' => $this->actualMinutes,
            'leaveTypeDescription' => $this->leaveTypeDescription,
        ];
    }

    public static function fromLivewire(mixed $value): static
    {
        if (! is_array($value)) {
            throw ValidationException::withMessages(['input' => 'Input data must be an array.']);
        }

        $validator = Validator::make($value, [
            'type' => 'required|string',
            'context' => 'present|string',
            'leaveType' => 'required|string',
            'duration' => 'required|string',
            'durationHours' => 'required|string',
            'durationDays' => 'required|numeric',
            'status' => 'required|string',
            'isHalfDay' => 'required|boolean',
            'timePeriod' => 'required|string',
            'timeRange' => 'required|string',
            'halfDayTime' => 'nullable|string',
            'startTime' => 'required|string',
            'endTime' => 'required|string',
            'actualMinutes' => 'required|integer',
            'leaveTypeDescription' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validatedData = $validator->validated();

        return new self(
            $validatedData['type'],
            $validatedData['context'],
            $validatedData['leaveType'],
            $validatedData['duration'],
            $validatedData['durationHours'],
            (float) $validatedData['durationDays'],
            $validatedData['status'],
            $validatedData['isHalfDay'],
            $validatedData['timePeriod'],
            $validatedData['timeRange'],
            $validatedData['halfDayTime'],
            $validatedData['startTime'],
            $validatedData['endTime'],
            $validatedData['actualMinutes'],
            $validatedData['leaveTypeDescription']
        );
    }
}
