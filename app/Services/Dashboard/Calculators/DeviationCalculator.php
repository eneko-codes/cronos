<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Calculators;

use Carbon\CarbonInterval;

/**
 * Service responsible for calculating deviations between different time metrics.
 */
class DeviationCalculator
{
    /**
     * Calculate daily deviations between schedule, attendance, and worked time.
     *
     * @param  mixed  $schedule  The schedule data for the day
     * @param  mixed  $attendance  The attendance data for the day
     * @param  mixed  $worked  The worked time data for the day
     * @param  mixed|null  $leave  The leave data for the day
     * @return array The calculated deviations
     */
    public function calculateDailyDeviations(
        $schedule,
        $attendance,
        $worked,
        $leave = null
    ) {
        $attendanceDuration = $attendance['duration'] ?? '0h 0m';
        $scheduleDuration = $schedule['duration'] ?? '0h 0m';
        $workedDuration = $worked['duration'] ?? '0h 0m';
        $isHalfDay = $leave['isHalfDay'] ?? false;

        $attendanceVsSchedule = $this->calculateDeviation(
            $attendanceDuration,
            $scheduleDuration,
            $isHalfDay
        );

        $workedVsSchedule = $this->calculateDeviation(
            $workedDuration,
            $scheduleDuration,
            $isHalfDay
        );

        $workedVsAttendance = $this->calculateDeviation(
            $workedDuration,
            $attendanceDuration,
            $isHalfDay
        );

        return [
            'attendanceVsScheduled' => $attendanceVsSchedule,
            'workedVsScheduled' => $workedVsSchedule,
            'workedVsAttendance' => $workedVsAttendance,
        ];
    }

    /**
     * Calculate overall deviations for a period.
     *
     * @param  array  $totals  The totals for the period
     * @return array The calculated overall deviations
     */
    public function calculateOverallDeviations(array $totals)
    {
        $attendanceVsSchedule = $this->calculateDeviation(
            \Carbon\CarbonInterval::minutes($totals['attendance'])->cascade()->format('%hh %dm'),
            \Carbon\CarbonInterval::minutes($totals['scheduled'])->cascade()->format('%hh %dm'),
            false
        );

        $workedVsSchedule = $this->calculateDeviation(
            \Carbon\CarbonInterval::minutes($totals['worked'])->cascade()->format('%hh %dm'),
            \Carbon\CarbonInterval::minutes($totals['scheduled'])->cascade()->format('%hh %dm'),
            false
        );

        $workedVsAttendance = $this->calculateDeviation(
            \Carbon\CarbonInterval::minutes($totals['worked'])->cascade()->format('%hh %dm'),
            \Carbon\CarbonInterval::minutes($totals['attendance'])->cascade()->format('%hh %dm'),
            false
        );

        return [
            'attendanceVsScheduled' => $attendanceVsSchedule,
            'workedVsScheduled' => $workedVsSchedule,
            'workedVsAttendance' => $workedVsAttendance,
        ];
    }

    /**
     * Calculate deviation between two time values.
     *
     * @param  string  $actual  The actual time value (e.g., "Xh Ym")
     * @param  string  $expected  The expected time value (e.g., "Xh Ym")
     * @param  bool  $isHalfDay  Whether the day is a half day
     * @return array The calculated deviation details
     */
    protected function calculateDeviation(string $actual, string $expected, bool $isHalfDay): array
    {
        $actualMinutes = CarbonInterval::fromString($actual)->totalMinutes;
        $expectedMinutes = CarbonInterval::fromString($expected)->totalMinutes;

        if ($isHalfDay) {
            $expectedMinutes = (int) round($expectedMinutes / 2);
        }

        $difference = (int) ($actualMinutes - $expectedMinutes);
        $percentage = $expectedMinutes > 0 ? ($difference / $expectedMinutes) * 100 : 0;

        return [
            'percentage' => (int) round($percentage),
            'differenceMinutes' => abs($difference),
            'tooltip' => $this->formatDeviationTooltip($difference),
            'shouldDisplay' => true,
        ];
    }

    /**
     * Format deviation tooltip.
     *
     * @param  int  $difference  The difference in minutes
     * @return string The formatted tooltip
     */
    protected function formatDeviationTooltip(int $difference): string
    {
        $isPositive = $difference >= 0;
        $formattedTime = CarbonInterval::minutes(abs($difference))->cascade()->format('%hh %dm');

        return sprintf(
            '%s%s',
            $isPositive ? '+' : '-',
            $formattedTime
        );
    }
}
