<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Data;

use App\DataTransferObjects\DailyWorkedData;
use App\DataTransferObjects\ProjectTaskSummaryData;
use App\DataTransferObjects\WorkedTimeEntry;
use App\Models\TimeEntry;
use Illuminate\Support\Collection;

/**
 * Service responsible for processing worked time data for the dashboard.
 */
class WorkedDataProcessorService
{
    /**
     * Process worked time data for a specific date.
     *
     * @param  string  $date  The date to process data for
     * @param  int  $userId  The user ID to process data for
     * @return DailyWorkedData The processed worked time data
     */
    public function processWorkedData(string $date, int $userId): DailyWorkedData
    {
        $entries = $this->findEntriesForDate($date, $userId);
        $durationInfo = $this->calculateDurationInfo($entries);
        $projectSummaries = $this->generateProjectSummaries($entries);

        return new DailyWorkedData(
            duration: $durationInfo['formatted'],
            projects: collect($projectSummaries),
            detailedEntries: $entries->map(fn (TimeEntry $entry) => new WorkedTimeEntry(
                project: $entry->project->name,
                task: $entry->task?->name,
                description: $entry->description ?? '',
                duration: $this->formatMinutesToHoursMinutes((int) ($entry->duration_seconds / 60)),
                status: $entry->status
            ))
        );
    }

    /**
     * Find time entries for a specific date.
     *
     * @param  string  $date  The date to find entries for
     * @param  int  $userId  The user ID to find entries for
     * @return Collection<TimeEntry> The found time entries
     */
    protected function findEntriesForDate(string $date, int $userId): Collection
    {
        return TimeEntry::where('user_id', $userId)
            ->whereDate('date', $date)
            ->with(['project', 'task'])
            ->get();
    }

    /**
     * Calculate duration information from time entries.
     *
     * @param  Collection<TimeEntry>  $entries  The time entries to calculate duration for
     * @return array{minutes: int, formatted: string} The duration information
     */
    protected function calculateDurationInfo(Collection $entries): array
    {
        $totalMinutes = $entries->sum(fn (TimeEntry $entry) => $entry->duration_seconds / 60);
        $formatted = $this->formatMinutesToHoursMinutes((int) $totalMinutes);

        return [
            'minutes' => (int) $totalMinutes,
            'formatted' => $formatted,
        ];
    }

    /**
     * Generate summaries of worked time by project.
     *
     * @param  Collection<TimeEntry>  $entries  The time entries to summarize
     * @return array<ProjectTaskSummaryData> The project summaries
     */
    protected function generateProjectSummaries(Collection $entries): array
    {
        $summaries = [];

        foreach ($entries->groupBy('proofhub_project_id') as $projectEntries) {
            $project = $projectEntries->first()->project;
            $totalMinutes = $projectEntries->sum(fn (TimeEntry $entry) => $entry->duration_seconds / 60);
            $uniqueTaskNames = $projectEntries
                ->pluck('task.name')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $summaries[] = new ProjectTaskSummaryData(
                name: $project->name,
                tasks: collect($uniqueTaskNames)
            );
        }

        return $summaries;
    }

    /**
     * Format minutes to hours and minutes string.
     *
     * @param  int  $minutes  The minutes to format
     * @return string The formatted time string
     */
    protected function formatMinutesToHoursMinutes(int $minutes): string
    {
        $hours = floor($minutes / 60);
        $remainingMinutes = abs($minutes % 60);

        return sprintf('%dh %dm', $hours, $remainingMinutes);
    }
}
