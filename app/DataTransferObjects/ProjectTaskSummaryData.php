<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Livewire\Wireable;
use Webmozart\Assert\Assert;

final readonly class ProjectTaskSummaryData implements Wireable
{
    public function __construct(
        public string $name,
        /** @var array<string> */
        public array $tasks
    ) {}

    public function toLivewire(): array
    {
        return [
            'name' => $this->name,
            'tasks' => $this->tasks,
        ];
    }

    public static function fromLivewire(mixed $value): static
    {
        Assert::isArray($value);
        Assert::keyExists($value, 'name');
        Assert::keyExists($value, 'tasks');

        return new self(
            $value['name'],
            $value['tasks']
        );
    }
}
