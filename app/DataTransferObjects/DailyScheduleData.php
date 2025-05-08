<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Livewire\Wireable;
use Webmozart\Assert\Assert;

final readonly class DailyScheduleData implements Wireable
{
    public function __construct(
        public string $duration,
        /** @var array<string> */
        public array $slots,
        public ?string $scheduleName
    ) {}

    public function toLivewire(): array
    {
        return [
            'duration' => $this->duration,
            'slots' => $this->slots,
            'scheduleName' => $this->scheduleName,
        ];
    }

    public static function fromLivewire(mixed $value): static
    {
        Assert::isArray($value);
        Assert::keyExists($value, 'duration');
        Assert::keyExists($value, 'slots');
        Assert::keyExists($value, 'scheduleName');

        return new self(
            $value['duration'],
            $value['slots'],
            $value['scheduleName']
        );
    }
}
