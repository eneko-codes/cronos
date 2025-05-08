<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Livewire\Wireable;
use Webmozart\Assert\Assert;

final readonly class DashboardTotals implements Wireable
{
    public function __construct(
        public int $scheduled,
        public int $attendance,
        public int $worked,
        public int $leave
    ) {}

    public function toLivewire(): array
    {
        return [
            'scheduled' => $this->scheduled,
            'attendance' => $this->attendance,
            'worked' => $this->worked,
            'leave' => $this->leave,
        ];
    }

    public static function fromLivewire(mixed $value): static
    {
        Assert::isArray($value);
        Assert::keyExists($value, 'scheduled');
        // Add other assertions as needed

        return new self(
            $value['scheduled'],
            $value['attendance'],
            $value['worked'],
            $value['leave']
        );
    }
}
