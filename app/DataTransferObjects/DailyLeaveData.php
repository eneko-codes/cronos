<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Livewire\Wireable;
use Webmozart\Assert\Assert;

final readonly class DailyLeaveData implements Wireable
{
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
        Assert::isArray($value);
        // Add assertions for all keys for robustness if desired
        Assert::keyExists($value, 'type');

        return new self(
            $value['type'],
            $value['context'],
            $value['leaveType'],
            $value['duration'],
            $value['durationHours'],
            $value['durationDays'],
            $value['status'],
            $value['isHalfDay'],
            $value['timePeriod'],
            $value['timeRange'],
            $value['halfDayTime'],
            $value['startTime'],
            $value['endTime'],
            $value['actualMinutes'],
            $value['leaveTypeDescription']
        );
    }
}
