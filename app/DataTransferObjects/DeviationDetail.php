<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Livewire\Wireable;
use Webmozart\Assert\Assert;

final readonly class DeviationDetail implements Wireable
{
    public function __construct(
        public int $percentage,
        public int $differenceMinutes,
        public string $tooltip,
        public bool $shouldDisplay
    ) {}

    public function toLivewire(): array
    {
        return [
            'percentage' => $this->percentage,
            'differenceMinutes' => $this->differenceMinutes,
            'tooltip' => $this->tooltip,
            'shouldDisplay' => $this->shouldDisplay,
        ];
    }

    public static function fromLivewire(mixed $value): static
    {
        Assert::isArray($value);
        Assert::keyExists($value, 'percentage');
        // Add other assertions as needed

        return new self(
            $value['percentage'],
            $value['differenceMinutes'],
            $value['tooltip'],
            $value['shouldDisplay']
        );
    }
}
