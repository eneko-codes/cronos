<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Wireable;

/**
 * Represents daily attendance data.
 */
final readonly class DailyAttendanceData implements Wireable
{
    /**
     * @param  string  $duration  The duration of attendance.
     * @param  bool  $isRemote  Whether the attendance was remote.
     * @param  array<string>  $times  An array of attendance times (e.g., clock-in/out pairs).
     */
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
        if (! is_array($value)) {
            throw ValidationException::withMessages(['input' => 'Input data must be an array.']);
        }

        $validator = Validator::make($value, [
            'duration' => 'required|string',
            'isRemote' => 'required|boolean',
            'times' => 'present|array',
            'times.*' => 'string',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validatedData = $validator->validated();

        return new self(
            $validatedData['duration'],
            $validatedData['isRemote'],
            $validatedData['times']
        );
    }
}
