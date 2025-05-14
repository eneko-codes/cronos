<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Calculators;

use App\DataTransferObjects\DailyAttendanceData;
use App\DataTransferObjects\DailyLeaveData;
use App\DataTransferObjects\DailyScheduleData;
use App\DataTransferObjects\DailyWorkedData;
use App\DataTransferObjects\DashboardTotals;
use App\DataTransferObjects\DeviationDetail;
use App\DataTransferObjects\DeviationMetrics;
use Carbon\CarbonInterval;

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
    ): DeviationMetrics {
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

        return new DeviationMetrics(
            attendanceVsScheduled: $attendanceVsSchedule,
            workedVsScheduled: $workedVsSchedule,
            workedVsAttendance: $workedVsAttendance
        );
    }

    /**
     * Calculate overall deviations for a period.
     *
     * @param  DashboardTotals  $totals  The totals for the period
     * @return DeviationMetrics The calculated overall deviations
     */
    public function calculateOverallDeviations(DashboardTotals $totals): DeviationMetrics
    {
        $attendanceVsSchedule = $this->calculateDeviation(
            CarbonInterval::minutes($totals->attendance)->cascade()->format('%hh %dm'),
            CarbonInterval::minutes($totals->scheduled)->cascade()->format('%hh %dm'),
            false
        );

        $workedVsSchedule = $this->calculateDeviation(
            CarbonInterval::minutes($totals->worked)->cascade()->format('%hh %dm'),
            CarbonInterval::minutes($totals->scheduled)->cascade()->format('%hh %dm'),
            false
        );

        $workedVsAttendance = $this->calculateDeviation(
            CarbonInterval::minutes($totals->worked)->cascade()->format('%hh %dm'),
            CarbonInterval::minutes($totals->attendance)->cascade()->format('%hh %dm'),
            false
        );

        return new DeviationMetrics(
            attendanceVsScheduled: $attendanceVsSchedule,
            workedVsScheduled: $workedVsSchedule,
            workedVsAttendance: $workedVsAttendance
        );
    }

    /**
     * Calculate deviation between two time values.
     *
     * @param  string  $actual  The actual time value (e.g., "Xh Ym")
     * @param  string  $expected  The expected time value (e.g., "Xh Ym")
     * @param  bool  $isHalfDay  Whether the day is a half day
     * @return DeviationDetail The calculated deviation details
     */
    protected function calculateDeviation(string $actual, string $expected, bool $isHalfDay): DeviationDetail
    {
        $actualMinutes = CarbonInterval::fromString($actual)->totalMinutes;
        $expectedMinutes = CarbonInterval::fromString($expected)->totalMinutes;

        if ($isHalfDay) {
            $expectedMinutes = (int) round($expectedMinutes / 2);
        }

        $difference = (int) ($actualMinutes - $expectedMinutes);
        $percentage = $expectedMinutes > 0 ? ($difference / $expectedMinutes) * 100 : 0;

        return new DeviationDetail(
            percentage: (int) round($percentage),
            differenceMinutes: abs($difference),
            tooltip: $this->formatDeviationTooltip($difference),
            shouldDisplay: true
        );
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
