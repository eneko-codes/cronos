<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\DataTransferObjects\Dashboard\DeviationData;
use App\Services\DurationFormatterService;
use Carbon\Carbon;
use Carbon\CarbonInterval;

class DeviationCalculator
{
    /**
     * Calculate daily deviations between schedule, attendance, and worked time.
     */
    public function calculateDailyDeviations(
        ?string $scheduleDuration,
        ?string $attendanceDuration,
        ?string $workedDuration,
        ?bool $isHalfDay = false,
        ?string $dateString = null
    ): array {
        // If date is in the future, return empty deviations
        if ($dateString && Carbon::parse($dateString)->startOfDay()->isFuture()) {
            return [
                'attendanceVsScheduled' => new DeviationData,
                'workedVsScheduled' => new DeviationData,
                'workedVsAttendance' => new DeviationData,
            ];
        }

        $attendanceVsSchedule = $this->calculateDeviation(
            $attendanceDuration ?? '0h 0m',
            $scheduleDuration ?? '0h 0m',
            $isHalfDay
        );

        $workedVsSchedule = $this->calculateDeviation(
            $workedDuration ?? '0h 0m',
            $scheduleDuration ?? '0h 0m',
            $isHalfDay
        );

        $workedVsAttendance = $this->calculateDeviation(
            $workedDuration ?? '0h 0m',
            $attendanceDuration ?? '0h 0m',
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
     */
    public function calculateOverallDeviations(array $totals): array
    {
        $attendanceVsSchedule = $this->calculateDeviation(
            DurationFormatterService::fromMinutes($totals['attendance']),
            DurationFormatterService::fromMinutes($totals['scheduled']),
            false
        );

        $workedVsSchedule = $this->calculateDeviation(
            DurationFormatterService::fromMinutes($totals['worked']),
            DurationFormatterService::fromMinutes($totals['scheduled']),
            false
        );

        $workedVsAttendance = $this->calculateDeviation(
            DurationFormatterService::fromMinutes($totals['worked']),
            DurationFormatterService::fromMinutes($totals['attendance']),
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
     */
    protected function calculateDeviation(string $actual, string $expected, bool $isHalfDay): DeviationData
    {
        $actualMinutes = CarbonInterval::fromString($actual)->totalMinutes;
        $expectedMinutes = CarbonInterval::fromString($expected)->totalMinutes;

        if ($isHalfDay) {
            $expectedMinutes = (int) round($expectedMinutes / 2);
        }

        $difference = (int) ($actualMinutes - $expectedMinutes);
        $percentage = $expectedMinutes > 0 ? ($difference / $expectedMinutes) * 100 : 0;

        return new DeviationData(
            percentage: (int) round($percentage),
            differenceMinutes: abs($difference),
            tooltip: $this->formatDeviationTooltip($difference),
            shouldDisplay: true,
        );
    }

    /**
     * Format deviation tooltip.
     */
    protected function formatDeviationTooltip(int $difference): string
    {
        $isPositive = $difference >= 0;
        $formattedTime = DurationFormatterService::fromMinutes(abs($difference));
        $sign = $isPositive ? '+' : '-';

        return $sign.$formattedTime;
    }
}
