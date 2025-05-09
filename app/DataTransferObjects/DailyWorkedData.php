<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Wireable;

/**
 * Represents daily worked time data, including project summaries and detailed entries.
 */
final readonly class DailyWorkedData implements Wireable
{
    /**
     * @param  string  $duration  The total duration of worked time.
     * @param  Collection<int, ProjectTaskSummaryData>  $projects  A collection of project task summaries.
     * @param  Collection<int, WorkedTimeEntry>  $detailedEntries  A collection of detailed worked time entries.
     */
    public function __construct(
        public string $duration,
        public Collection $projects,
        public Collection $detailedEntries
    ) {}

    public function toLivewire(): array
    {
        return [
            'duration' => $this->duration,
            'projects' => $this->projects->map(fn (ProjectTaskSummaryData $item) => $item->toLivewire())->all(),
            'detailedEntries' => $this->detailedEntries->map(fn (WorkedTimeEntry $item) => $item->toLivewire())->all(),
        ];
    }

    public static function fromLivewire(mixed $value): static
    {
        if (! is_array($value)) {
            throw ValidationException::withMessages(['input' => 'Input data must be an array.']);
        }

        $validator = Validator::make($value, [
            'duration' => 'required|string',
            'projects' => 'present|array',
            'detailedEntries' => 'present|array',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validatedData = $validator->validated();

        return new self(
            $validatedData['duration'],
            collect($validatedData['projects'])->map(fn (array $itemData) => ProjectTaskSummaryData::fromLivewire($itemData)),
            collect($validatedData['detailedEntries'])->map(fn (array $itemData) => WorkedTimeEntry::fromLivewire($itemData))
        );
    }
}
