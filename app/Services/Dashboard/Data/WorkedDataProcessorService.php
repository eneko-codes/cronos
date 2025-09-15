<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Data;

use App\Exceptions\DataTransferObjectException;
use App\Models\TimeEntry;
use Carbon\CarbonInterval;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

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
     * @return array The processed worked time data as an array
     */
    public function processWorkedData(string $date, int $userId): array
    {
        try {
            $entries = $this->findEntriesForDate($date, $userId);
            $durationInfo = $this->calculateDurationInfo($entries);
            $projectSummaries = $this->generateProjectSummaries($entries);

            $detailedEntries = $entries->map(function (TimeEntry $entry) {
                return [
                    'project' => optional($entry->project)->title ?? '—',
                    'task' => optional($entry->task)->name,
                    'description' => $entry->description,
                    'duration' => CarbonInterval::seconds((int) $entry->duration_seconds)
                        ->cascade()
                        ->format('%hh %Im'),
                ];
            });

            return [
                'entries' => $entries,
                'duration' => $durationInfo['formatted'],
                'projects' => collect($projectSummaries),
                'detailedEntries' => $detailedEntries,
            ];
        } catch (\Exception $e) {
            Log::error('Error processing worked data', [
                'date' => $date,
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new DataTransferObjectException(
                "Failed to process worked data for date {$date} and user {$userId}: {$e->getMessage()}",
                0,
                $e
            );
        }
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
        $totalSeconds = $entries->sum(fn (TimeEntry $entry) => $entry->duration_seconds);

        // Return empty string for no worked time instead of "0h 00m"
        if ($totalSeconds <= 0 || $entries->isEmpty()) {
            return [
                'minutes' => 0,
                'formatted' => '',
            ];
        }

        $interval = CarbonInterval::seconds((int) $totalSeconds)->cascade();
        $formatted = $interval->format('%hh %Im');

        return [
            'minutes' => (int) $interval->totalMinutes,
            'formatted' => $formatted,
        ];
    }

    /**
     * Generate summaries of worked time by project.
     *
     * @param  Collection<TimeEntry>  $entries  The time entries to summarize
     * @return array<int, array{title: string, tasks: array<string>}>
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
                // Align key names with the Blade view expectations
                'title' => optional($project)->title ?? 'Unknown project',
                'tasks' => $uniqueTaskNames,
            ];
        }

        return $summaries;
    }
}
