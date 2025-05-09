<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Wireable;

/**
 * Represents the details of a deviation between two time values.
 */
final readonly class DeviationDetail implements Wireable
{
    /**
     * @param  int  $percentage  The deviation percentage.
     * @param  int  $differenceMinutes  The difference in minutes.
     * @param  string  $tooltip  A tooltip providing more information about the deviation.
     * @param  bool  $shouldDisplay  Whether this deviation detail should be displayed.
     */
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
        if (! is_array($value)) {
            throw ValidationException::withMessages(['input' => 'Input data must be an array.']);
        }

        $validator = Validator::make($value, [
            'percentage' => 'required|integer',
            'differenceMinutes' => 'required|integer',
            'tooltip' => 'required|string',
            'shouldDisplay' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validatedData = $validator->validated();

        return new self(
            $validatedData['percentage'],
            $validatedData['differenceMinutes'],
            $validatedData['tooltip'],
            $validatedData['shouldDisplay']
        );
    }
}
