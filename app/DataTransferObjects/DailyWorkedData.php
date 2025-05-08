<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Illuminate\Support\Collection;
use Livewire\Wireable;
use Webmozart\Assert\Assert;

final readonly class DailyWorkedData implements Wireable
{
    /**
     * @param  Collection<int, ProjectTaskSummaryData>  $projects
     * @param  Collection<int, WorkedTimeEntry>  $detailedEntries
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
        Assert::isArray($value);
        Assert::keyExists($value, 'duration');
        Assert::keyExists($value, 'projects');
        Assert::keyExists($value, 'detailedEntries');
        Assert::isArray($value['projects']);
        Assert::isArray($value['detailedEntries']);

        return new self(
            $value['duration'],
            collect($value['projects'])->map(fn (array $itemData) => ProjectTaskSummaryData::fromLivewire($itemData)),
            collect($value['detailedEntries'])->map(fn (array $itemData) => WorkedTimeEntry::fromLivewire($itemData))
        );
    }
}
