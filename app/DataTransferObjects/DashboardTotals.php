<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Wireable;

/**
 * Represents total values for the dashboard.
 */
final readonly class DashboardTotals implements Wireable
{
    /**
     * @param  int  $scheduled  Total scheduled time in minutes.
     * @param  int  $attendance  Total attendance time in minutes.
     * @param  int  $worked  Total worked time in minutes.
     * @param  int  $leave  Total leave time in minutes.
     */
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
        if (! is_array($value)) {
            throw ValidationException::withMessages(['input' => 'Input data must be an array.']);
        }

        $validator = Validator::make($value, [
            'scheduled' => 'required|integer',
            'attendance' => 'required|integer',
            'worked' => 'required|integer',
            'leave' => 'required|integer',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validatedData = $validator->validated();

        return new self(
            $validatedData['scheduled'],
            $validatedData['attendance'],
            $validatedData['worked'],
            $validatedData['leave']
        );
    }
}
