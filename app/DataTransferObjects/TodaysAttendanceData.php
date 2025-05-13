<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Livewire\Wireable;

final readonly class TodaysAttendanceData implements Wireable
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $timeInfo,
        public readonly string $duration,
        public readonly bool $isRemote,
        public readonly bool $clockedIn,
    ) {}

    public function toLivewire(): array
    {
        return [
            'status' => $this->status,
            'timeInfo' => $this->timeInfo,
            'duration' => $this->duration,
            'isRemote' => $this->isRemote,
            'clockedIn' => $this->clockedIn,
        ];
    }

    public static function fromLivewire(mixed $value): static
    {
        return new self(
            $value['status'],
            $value['timeInfo'],
            $value['duration'],
            $value['isRemote'],
            $value['clockedIn']
        );
    }
}
