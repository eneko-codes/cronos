<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Livewire\Wireable;

final readonly class TodaysScheduleData implements Wireable
{
    public function __construct(
        public readonly string $duration,
        public readonly string $name,
    ) {}

    public function toLivewire(): array
    {
        return ['duration' => $this->duration, 'name' => $this->name];
    }

    public static function fromLivewire(mixed $value): static
    {
        return new self($value['duration'], $value['name']);
    }
}
