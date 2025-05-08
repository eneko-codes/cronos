<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Livewire\Wireable;
use Webmozart\Assert\Assert;

final readonly class DailyAttendanceData implements Wireable
{
    public function __construct(
        public string $duration,
        public bool $isRemote,
        /** @var array<string> */
        public array $times
    ) {}

    public function toLivewire(): array
    {
        return [
            'duration' => $this->duration,
            'isRemote' => $this->isRemote,
            'times' => $this->times,
        ];
    }

    public static function fromLivewire(mixed $value): static
    {
        Assert::isArray($value);
        Assert::keyExists($value, 'duration');
        Assert::keyExists($value, 'isRemote');
        Assert::keyExists($value, 'times');

        return new self(
            $value['duration'],
            $value['isRemote'],
            $value['times']
        );
    }
}
