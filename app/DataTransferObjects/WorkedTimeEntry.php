<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Wireable;

/**
 * Represents a single entry of worked time.
 */
final readonly class WorkedTimeEntry implements Wireable
{
    /**
     * @param  string  $project  The project associated with the time entry.
     * @param  string|null  $task  The task associated with the time entry, if any.
     * @param  string  $description  A description of the work done.
     * @param  string  $duration  The duration of the work.
     * @param  string  $status  The status of the time entry.
     */
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
        if (! is_array($value)) {
            throw ValidationException::withMessages(['input' => 'Input data must be an array.']);
        }

        $validator = Validator::make($value, [
            'project' => 'required|string',
            'task' => 'nullable|string',
            'description' => 'present|string',
            'duration' => 'required|string',
            'status' => 'required|string',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validatedData = $validator->validated();

        return new self(
            $validatedData['project'],
            $validatedData['task'],
            $validatedData['description'],
            $validatedData['duration'],
            $validatedData['status']
        );
    }
}
