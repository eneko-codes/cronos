<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

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
     * @return array{periodData: Collection<string, array>, dashboardTotals: array, totalDeviationsDetails: array|null}
     */
    public function aggregatePeriodData(
        User $user,
        Carbon $startDate,
        Carbon $endDate,
        bool $showDeviations
    ): array {
        $rawData = $user->getDataForDateRange($startDate, $endDate);

        /** @var Collection<string, array> */
        $periodData = $this->processPeriodData($rawData, $startDate, $endDate, $showDeviations, $user);
        $totals = $this->totalsCalculator->calculateTotals($periodData);
        $deviations = $showDeviations ? $this->deviationCalculator->calculateOverallDeviations($totals) : null;

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
     * @return Collection<string, array>
     */
    protected function processPeriodData(
        array $data,
        Carbon $start,
        Carbon $end,
        bool $showDeviations,
        User $user
    ): Collection {
        $dates = new Collection;
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $dateString = $cursor->toDateString();

            $scheduleArr = $this->scheduleProcessor->processScheduleData($data['schedules'], $dateString);
            $leaveArr = $this->leaveProcessor->processLeaveData($data['leaves'], $dateString, $data['schedules']);
            $attendanceArr = $this->attendanceProcessor->processAttendanceData($data['attendances'], $dateString);
            $workedArr = $this->workedProcessor->processWorkedData($dateString, $user->id);

            $dailyDeviationDetailsArr = $showDeviations
                ? $this->deviationCalculator->calculateDailyDeviations(
                    $scheduleArr,
                    $attendanceArr,
                    $workedArr,
                    $leaveArr ?? []
                )
                : null;

            $dates->put($dateString, [
                'date' => $dateString,
                'scheduled' => $scheduleArr,
                'leave' => $leaveArr,
                'attendance' => $attendanceArr,
                'worked' => $workedArr,
                'deviationDetails' => $dailyDeviationDetailsArr,
            ]);

            $cursor->addDay();
        }

        return $dates;
    }
}
