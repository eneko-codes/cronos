<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Wireable;

/**
 * Represents daily schedule data.
 */
final readonly class DailyScheduleData implements Wireable
{
    /**
     * @param  string  $duration  The duration of the schedule.
     * @param  array<string>  $slots  An array of time slots for the schedule.
     * @param  string|null  $scheduleName  The name of the schedule, if any.
     */
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
        if (! is_array($value)) {
            throw ValidationException::withMessages(['input' => 'Input data must be an array.']);
        }

        $validator = Validator::make($value, [
            'duration' => 'required|string',
            'slots' => 'present|array',
            'slots.*' => 'string',
            'scheduleName' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validatedData = $validator->validated();

        return new self(
            $validatedData['duration'],
            $validatedData['slots'],
            $validatedData['scheduleName']
        );
    }
}
