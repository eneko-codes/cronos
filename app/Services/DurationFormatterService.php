<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonInterval;

/**
 * Service for formatting durations consistently across the application.
 *
 * Provides centralized duration formatting to eliminate code duplication.
 * Can be used statically for convenience in model accessors and closures.
 */
class DurationFormatterService
{
    /**
     * Format duration from seconds to "Xh Ym" format.
     *
     * @param  int|float  $seconds  Duration in seconds
     * @return string Formatted duration (e.g., "8h 30m") or empty string if zero
     */
    public static function fromSeconds(int|float $seconds): string
    {
        $seconds = (int) round($seconds);

        if ($seconds <= 0) {
            return '';
        }

        return CarbonInterval::seconds($seconds)->cascade()->format('%hh %Im');
    }

    /**
     * Format duration from minutes to "Xh Ym" format.
     *
     * @param  int|float  $minutes  Duration in minutes
     * @return string Formatted duration (e.g., "8h 30m") or empty string if zero
     */
    public static function fromMinutes(int|float $minutes): string
    {
        $minutes = (int) round($minutes);

        if ($minutes <= 0) {
            return '';
        }

        return CarbonInterval::minutes($minutes)->cascade()->format('%hh %Im');
    }

    /**
     * Format duration from a CarbonInterval to "Xh Ym" format.
     *
     * @param  CarbonInterval  $interval  The CarbonInterval to format
     * @return string Formatted duration (e.g., "8h 30m") or empty string if zero
     */
    public static function fromInterval(CarbonInterval $interval): string
    {
        $totalMinutes = (int) $interval->totalMinutes;

        if ($totalMinutes <= 0) {
            return '';
        }

        return $interval->cascade()->format('%hh %Im');
    }
}
