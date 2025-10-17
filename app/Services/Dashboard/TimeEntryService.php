<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\TimeEntry;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TimeEntryService
{
    /**
     * Get worked time data for a specific date.
     */
    public function getWorkedTimeForDate(string $date, int $userId): array
    {
        $entries = $this->findEntriesForDate($date, $userId);
        $durationInfo = $this->calculateDurationInfo($entries);
        $projectSummaries = $this->generateProjectSummaries($entries);

        $detailedEntries = $entries->map(function (TimeEntry $entry): array {
            return [
                'project' => optional($entry->project)->title ?? '—',
                'task' => optional($entry->task)->name,
                'description' => $entry->description,
                'duration' => $entry->formatted_duration,
            ];
        });

        return [
            'entries' => $entries,
            'duration' => $durationInfo['formatted'],
            'projects' => collect($projectSummaries),
            'detailedEntries' => $detailedEntries,
        ];
    }

    /**
     * Find time entries for a specific date.
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
     */
    protected function calculateDurationInfo(Collection $entries): array
    {
        $totalSeconds = $entries->sum(fn (TimeEntry $entry) => $entry->duration_seconds);

        if ($totalSeconds <= 0 || $entries->isEmpty()) {
            return [
                'minutes' => 0,
                'formatted' => '',
            ];
        }

        $interval = \Carbon\CarbonInterval::seconds((int) $totalSeconds)->cascade();
        $formatted = $interval->format('%hh %Im');

        return [
            'minutes' => (int) $interval->totalMinutes,
            'formatted' => $formatted,
        ];
    }

    /**
     * Generate summaries of worked time by project.
     */
    protected function generateProjectSummaries(Collection $entries): array
    {
        $summaries = [];

        foreach ($entries->groupBy('proofhub_project_id') as $projectEntries) {
            $project = $projectEntries->first()->project;
            $uniqueTaskNames = $projectEntries
                ->pluck('task.name')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $summaries[] = [
                'title' => optional($project)->title ?? 'Unknown project',
                'tasks' => $uniqueTaskNames,
            ];
        }

        return $summaries;
    }
}
