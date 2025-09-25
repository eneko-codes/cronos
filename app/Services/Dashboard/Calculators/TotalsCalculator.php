<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Calculators;

use Illuminate\Support\Collection;

/**
 * Service responsible for calculating dashboard totals from period data.
 */
class TotalsCalculator
{
    /**
     * Calculate totals from period data.
     *
     * @param  Collection<string, array>  $periodData  The period data to calculate totals from
     */
    public function calculateTotals(Collection $periodData): array
    {
        $totals = [
            'scheduled' => 0,
            'attendance' => 0,
            'worked' => 0,
            'leave' => 0,
        ];

        foreach ($periodData as $dayData) {
            // Skip total rows that may be included in the data
            if (isset($dayData['isTotalRow']) && $dayData['isTotalRow']) {
                continue;
            }

            $totals['scheduled'] += $this->timeToMinutes($dayData['scheduled']['duration'] ?? '');
            $totals['attendance'] += $this->timeToMinutes($dayData['attendance']['duration'] ?? '');
            $totals['worked'] += $this->timeToMinutes($dayData['worked']['duration'] ?? '');
            $totals['leave'] += $this->timeToMinutes($dayData['leave']['duration'] ?? '');
        }

        return $totals;
    }

    /**
     * Convert time string to minutes.
     *
     * @param  string  $time  Time string in format "Xh Ym" or empty string
     */
    protected function timeToMinutes(string $time): int
    {
        if (empty(trim($time))) {
            return 0;
        }

        // Try to parse using CarbonInterval first for consistency
        try {
            return (int) \Carbon\CarbonInterval::fromString($time)->totalMinutes;
        } catch (\Exception $e) {
            // Fallback to manual parsing for older format strings
            $parts = explode(' ', trim($time));
            $hours = 0;
            $minutes = 0;

            foreach ($parts as $part) {
                $part = trim($part);
                if (str_ends_with($part, 'h')) {
                    $hours = (int) rtrim($part, 'h');
                } elseif (str_ends_with($part, 'm')) {
                    $minutes = (int) rtrim($part, 'm');
                }
            }

            return ($hours * 60) + $minutes;
        }
    }

    /**
     * Format minutes into hours and minutes string.
     *
     * @param  int  $minutes  The total minutes
     */
    public function formatMinutesToHoursMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return '';
        }

        return \Carbon\CarbonInterval::minutes($minutes)->cascade()->format('%hh %Im');
    }

    /**
     * Get formatted totals for display.
     *
     * @param  array  $totals  The calculated totals in minutes
     * @return array The formatted totals for display
     */
    public function getFormattedTotals(array $totals): array
    {
        return [
            'scheduled' => $this->formatMinutesToHoursMinutes($totals['scheduled']),
            'attendance' => $this->formatMinutesToHoursMinutes($totals['attendance']),
            'worked' => $this->formatMinutesToHoursMinutes($totals['worked']),
            'leave' => $this->formatMinutesToHoursMinutes($totals['leave']),
        ];
    }
}
