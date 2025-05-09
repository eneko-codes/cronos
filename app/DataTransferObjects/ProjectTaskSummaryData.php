<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Wireable;

/**
 * Represents a summary of tasks for a specific project.
 */
final readonly class ProjectTaskSummaryData implements Wireable
{
    /**
     * @param  string  $name  The name of the project.
     * @param  Collection<int, string>  $tasks  A collection of task names associated with the project.
     */
    public function __construct(
        public string $name,
        public Collection $tasks
    ) {}

    public function toLivewire(): array
    {
        return [
            'name' => $this->name,
            'tasks' => $this->tasks->all(),
        ];
    }

    public static function fromLivewire(mixed $value): static
    {
        if (! is_array($value)) {
            throw ValidationException::withMessages(['input' => 'Input data must be an array.']);
        }

        $validator = Validator::make($value, [
            'name' => 'required|string',
            'tasks' => 'present|array',
            'tasks.*' => 'string',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validatedData = $validator->validated();

        return new self(
            $validatedData['name'],
            collect($validatedData['tasks'])
        );
    }
}
