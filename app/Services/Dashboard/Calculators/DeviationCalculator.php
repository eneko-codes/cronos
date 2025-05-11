<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Calculators;

use App\DataTransferObjects\DailyAttendanceData;
use App\DataTransferObjects\DailyDeviationDetails;
use App\DataTransferObjects\DailyLeaveData;
use App\DataTransferObjects\DailyScheduleData;
use App\DataTransferObjects\DailyWorkedData;
use App\DataTransferObjects\DashboardTotals;
use App\DataTransferObjects\DeviationDetail;
use App\DataTransferObjects\OverallDeviationDetails;

/**
 * Service responsible for calculating deviations between different time metrics.
 */
class DeviationCalculator
{
    /**
     * Calculate daily deviations between schedule, attendance, and worked time.
     *
     * @param  DailyScheduleData  $schedule  The schedule data for the day
     * @param  DailyAttendanceData  $attendance  The attendance data for the day
     * @param  DailyWorkedData  $worked  The worked time data for the day
     * @param  DailyLeaveData  $leave  The leave data for the day
     */
    public function calculateDailyDeviations(
        DailyScheduleData $schedule,
        DailyAttendanceData $attendance,
        DailyWorkedData $worked,
        DailyLeaveData $leave
    ): DailyDeviationDetails {
        $attendanceVsSchedule = $this->calculateDeviation(
            $attendance->duration,
            $schedule->duration,
            $leave->isHalfDay
        );

        $workedVsSchedule = $this->calculateDeviation(
            $worked->duration,
            $schedule->duration,
            $leave->isHalfDay
        );

        $workedVsAttendance = $this->calculateDeviation(
            $worked->duration,
            $attendance->duration,
            $leave->isHalfDay
        );

        return new DailyDeviationDetails(
            attendanceVsScheduled: $attendanceVsSchedule,
            workedVsScheduled: $workedVsSchedule,
            workedVsAttendance: $workedVsAttendance
        );
    }

    /**
     * Calculate overall deviations for a period.
     *
     * @param  DashboardTotals  $totals  The totals for the period
     * @return OverallDeviationDetails The calculated overall deviations
     */
    public function calculateOverallDeviations(DashboardTotals $totals): OverallDeviationDetails
    {
        $attendanceVsSchedule = $this->calculateDeviation(
            $this->formatMinutesToHoursMinutes($totals->attendance),
            $this->formatMinutesToHoursMinutes($totals->scheduled),
            false
        );

        $workedVsSchedule = $this->calculateDeviation(
            $this->formatMinutesToHoursMinutes($totals->worked),
            $this->formatMinutesToHoursMinutes($totals->scheduled),
            false
        );

        $workedVsAttendance = $this->calculateDeviation(
            $this->formatMinutesToHoursMinutes($totals->worked),
            $this->formatMinutesToHoursMinutes($totals->attendance),
            false
        );

        return new OverallDeviationDetails(
            attendanceVsScheduled: $attendanceVsSchedule,
            workedVsScheduled: $workedVsSchedule,
            workedVsAttendance: $workedVsAttendance
        );
    }

    /**
     * Calculate deviation between two time values.
     *
     * @param  string  $actual  The actual time value
     * @param  string  $expected  The expected time value
     * @param  bool  $isHalfDay  Whether the day is a half day
     * @return DeviationDetail The calculated deviation details
     */
    protected function calculateDeviation(string $actual, string $expected, bool $isHalfDay): DeviationDetail
    {
        $actualMinutes = $this->timeToMinutes($actual);
        $expectedMinutes = $this->timeToMinutes($expected);

        if ($isHalfDay) {
            $expectedMinutes = (int) round($expectedMinutes / 2);
        }

        $difference = $actualMinutes - $expectedMinutes;
        $percentage = $expectedMinutes > 0 ? ($difference / $expectedMinutes) * 100 : 0;

        return new DeviationDetail(
            percentage: (int) round($percentage),
            differenceMinutes: abs($difference),
            tooltip: $this->formatDeviationTooltip($difference),
            shouldDisplay: true
        );
    }

    /**
     * Convert time string to minutes.
     *
     * @param  string  $time  Time string in format "Xh Ym"
     * @return int The time in minutes
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

    /**
     * Format deviation tooltip.
     *
     * @param  int  $difference  The difference in minutes
     * @return string The formatted tooltip
     */
    protected function formatDeviationTooltip(int $difference): string
    {
        $isPositive = $difference >= 0;
        $formattedTime = $this->formatMinutesToHoursMinutes(abs($difference));

        return sprintf(
            '%s%s',
            $isPositive ? '+' : '-',
            $formattedTime
        );
    }
}
