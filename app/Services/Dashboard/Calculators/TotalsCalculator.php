<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Calculators;

use Carbon\CarbonInterval;
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
            $totals['scheduled'] += $this->timeToMinutes($dayData['scheduled']['duration'] ?? '0h 0m');
            $totals['attendance'] += $this->timeToMinutes($dayData['attendance']['duration'] ?? '0h 0m');
            $totals['worked'] += $this->timeToMinutes($dayData['worked']['duration'] ?? '0h 0m');
            $totals['leave'] += $this->timeToMinutes($dayData['leave']['duration'] ?? '0h 0m');
        }

        return $totals;
    }

    /**
     * Convert time string to minutes.
     *
     * @param  string  $time  Time string in format "Xh Ym"
     */
    protected function timeToMinutes(string $time): int
    {
        if (empty($time)) {
            return 0;
        }

        $parts = explode(' ', $time);
        $hours = 0;
        $minutes = 0;

        foreach ($parts as $part) {
            if (str_ends_with($part, 'h')) {
                $hours = (int) rtrim($part, 'h');
            } elseif (str_ends_with($part, 'm')) {
                $minutes = (int) rtrim($part, 'm');
            }
        }

        return ($hours * 60) + $minutes;
    }

    /**
     * Format minutes into hours and minutes string.
     *
     * @param  int  $minutes  The total minutes
     */
    protected function formatMinutesToHoursMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return '';
        }

        return CarbonInterval::minutes($minutes)->cascade()->format('%hh %Im');
    }
}
