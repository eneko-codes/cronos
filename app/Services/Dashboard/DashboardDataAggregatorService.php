<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\DataTransferObjects\DailyLeaveData;
use App\DataTransferObjects\DashboardTotals;
use App\DataTransferObjects\DeviationMetrics;
use App\DataTransferObjects\PeriodDayData;
use App\Models\User;
use App\Services\Dashboard\Calculators\DeviationCalculator;
use App\Services\Dashboard\Calculators\TotalsCalculator;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Service responsible for aggregating and coordinating dashboard data processing.
 * This service delegates specific data processing to specialized services and
 * handles the overall data flow and period processing.
 */
class DashboardDataAggregatorService
{
    public function __construct(
        private \App\Services\Dashboard\Data\LeaveDataProcessorService $leaveProcessor,
        private \App\Services\Dashboard\Data\AttendanceDataProcessorService $attendanceProcessor,
        private \App\Services\Dashboard\Data\ScheduleDataProcessorService $scheduleProcessor,
        private \App\Services\Dashboard\Data\WorkedDataProcessorService $workedProcessor,
        private DeviationCalculator $deviationCalculator,
        private TotalsCalculator $totalsCalculator
    ) {}

    /**
     * Generates all processed data required for the user dashboard.
     *
     * @param  User  $user  The user for whom to generate data.
     * @param  Carbon  $startDate  The start date of the period.
     * @param  Carbon  $endDate  The end date of the period.
     * @param  bool  $showDeviations  Flag to indicate if deviation details should be calculated.
     * @return array{periodData: Collection<string, PeriodDayData>, dashboardTotals: DashboardTotals, totalDeviationsDetails: DeviationMetrics|null}
     */
    public function aggregatePeriodData(
        User $user,
        Carbon $startDate,
        Carbon $endDate,
        bool $showDeviations
    ): array {
        $rawData = $user->getDataForDateRange($startDate, $endDate);

        /** @var Collection<string, PeriodDayData> */
        $periodData = $this->processPeriodData($rawData, $startDate, $endDate, $showDeviations, $user);
        /** @var DashboardTotals */
        $totals = $this->totalsCalculator->calculateTotals($periodData);
        /** @var DeviationMetrics|null */
        $deviations = $showDeviations ? $this->deviationCalculator->calculateOverallDeviations($totals) : null;

        /** @var array{periodData: Collection<string, PeriodDayData>, dashboardTotals: DashboardTotals, totalDeviationsDetails: DeviationMetrics|null} */
        $result = [
            'periodData' => $periodData,
            'dashboardTotals' => $totals,
            'totalDeviationsDetails' => $deviations,
        ];

        $newTotals = $this->totalsCalculator->calculateTotals($result['periodData']);
        $result['dashboardTotals'] = $newTotals;
        if ($showDeviations) {
            $result['totalDeviationsDetails'] = $this->deviationCalculator->calculateOverallDeviations($newTotals);
        }

        return $result;
    }

    /**
     * Process data for each day in the period.
     *
     * @param  array  $data  Raw data from User::getDataForDateRange() method
     * @param  Carbon  $start  Start date of the period
     * @param  Carbon  $end  End date of the period
     * @param  bool  $showDeviations  Whether to calculate deviations
     * @param  User  $user  The user to process data for
     * @return Collection<string, PeriodDayData>
     */
    protected function processPeriodData(
        array $data,
        Carbon $start,
        Carbon $end,
        bool $showDeviations,
        User $user
    ): Collection {
        /** @var Collection<string, PeriodDayData> */
        $dates = new Collection;
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $dateString = $cursor->toDateString();

            $scheduleDto = $this->scheduleProcessor->processScheduleData($data['schedules'], $dateString);
            $leaveDto = $this->leaveProcessor->processLeaveData($data['leaves'], $dateString, $data['schedules']);
            $attendanceDto = $this->attendanceProcessor->processAttendanceData($data['attendances'], $dateString);
            $workedDto = $this->workedProcessor->processWorkedData($dateString, $user->id);

            $dailyDeviationDetailsDto = $showDeviations
                ? $this->deviationCalculator->calculateDailyDeviations(
                    $scheduleDto,
                    $attendanceDto,
                    $workedDto,
                    $leaveDto ?? new DailyLeaveData(
                        type: '',
                        context: '',
                        leaveType: '',
                        duration: '0h 0m',
                        durationHours: '0h 0m',
                        durationDays: 0,
                        status: '',
                        isHalfDay: false,
                        timePeriod: 'full-day',
                        timeRange: '',
                        halfDayTime: null,
                        startTime: '',
                        endTime: '',
                        actualMinutes: 0,
                        leaveTypeDescription: null
                    )
                )
                : null;

            $dates->put($dateString, new PeriodDayData(
                date: $dateString,
                scheduled: $scheduleDto,
                leave: $leaveDto,
                attendance: $attendanceDto,
                worked: $workedDto,
                deviationDetails: $dailyDeviationDetailsDto
            ));

            $cursor->addDay();
        }

        return $dates;
    }
}
