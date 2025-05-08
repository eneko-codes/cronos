<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Livewire\Wireable;
use Webmozart\Assert\Assert;

final readonly class WorkedTimeEntry implements Wireable
{
    public function __construct(
        public string $project,
        public ?string $task,
        public string $description,
        public string $duration,
        public string $status
    ) {}

    public function toLivewire(): array
    {
        return [
            'project' => $this->project,
            'task' => $this->task,
            'description' => $this->description,
            'duration' => $this->duration,
            'status' => $this->status,
        ];
    }

    public static function fromLivewire(mixed $value): static
    {
        Assert::isArray($value);
        Assert::keyExists($value, 'project');
        // Add other key assertions as needed

        return new self(
            $value['project'],
            $value['task'],
            $value['description'],
            $value['duration'],
            $value['status']
        );
    }
}
