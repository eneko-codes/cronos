<?php

declare(strict_types=1);

namespace App\Traits;

trait FormatsDurationsTrait
{
    /**
     * Utility function to format total minutes into a human-readable "Xh Ym" string.
     *
     * @param  float  $minutes  Total minutes.
     * @return string Formatted duration string.
     */
    public function formatMinutesToHoursMinutes(float $minutes): string
    {
        if ($minutes < 0) {
            // Consistent handling for negative values, can be adjusted as needed
            $minutes = 0;
        }

        $totalHours = floor($minutes / 60);
        $remainingMinutes = fmod($minutes, 60); // Use fmod for float precision

        return sprintf('%dh %dm', (int) $totalHours, (int) $remainingMinutes);
    }

    /**
     * Utility function to convert a duration string ("Xh Ym") into total minutes.
     *
     * @param  string  $duration  The duration string (e.g., "8h 15m", "30m", "2h").
     * @return int Total minutes.
     */
    protected function durationToMinutes(string $duration): int
    {
        $hours = 0;
        $minutes = 0;

        // Regex is more robust for various formats like "2h", "30m", "8h 15m"
        preg_match('/(?:(\d+)h)?\s*(?:(\d+)m)?/', $duration, $matches);

        if (! empty($matches[1])) {
            $hours = (int) $matches[1];
        }
        if (! empty($matches[2])) {
            $minutes = (int) $matches[2];
        }

        return ($hours * 60) + $minutes;
    }
}
