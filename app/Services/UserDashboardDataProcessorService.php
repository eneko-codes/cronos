<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\User\GetDataForDateRange;
use App\DataTransferObjects\DailyAttendanceData;
use App\DataTransferObjects\DailyDeviationDetails;
use App\DataTransferObjects\DailyLeaveData;
use App\DataTransferObjects\DailyScheduleData;
use App\DataTransferObjects\DailyWorkedData;
use App\DataTransferObjects\DashboardTotals;
use App\DataTransferObjects\DeviationDetail;
use App\DataTransferObjects\OverallDeviationDetails;
use App\DataTransferObjects\PeriodDayData;
use App\DataTransferObjects\ProjectTaskSummaryData;
use App\DataTransferObjects\WorkedTimeEntry;
use App\Models\User;
use App\Traits\FormatsDurationsTrait;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

// We will add models and other necessary classes as we move methods

class UserDashboardDataProcessorService
{
    use FormatsDurationsTrait;

    private GetDataForDateRange $getDataForDateRangeAction;

    public function __construct(GetDataForDateRange $getDataForDateRangeAction)
    {
        $this->getDataForDateRangeAction = $getDataForDateRangeAction;
    }

    /**
     * Generates all processed data required for the user dashboard.
     *
     * @param  User  $user  The user for whom to generate data.
     * @param  Carbon  $startDate  The start date of the period.
     * @param  Carbon  $endDate  The end date of the period.
     * @param  bool  $showDeviations  Flag to indicate if deviation details should be calculated.
     * @return array{periodData: Collection<string, PeriodDayData>, dashboardTotals: DashboardTotals, totalDeviationsDetails: OverallDeviationDetails|null}
     */
    public function generateProcessedData(User $user, Carbon $startDate, Carbon $endDate, bool $showDeviations): array
    {
        $rawData = $this->getDataForDateRangeAction->handle($user, $startDate, $endDate);

        $periodDataCollection = $this->processPeriodData($rawData, $startDate, $endDate, $showDeviations, $user);
        $dashboardTotalsDto = $this->calculatePeriodTotals($periodDataCollection);

        $overallDeviationsDto = null;
        if ($showDeviations) {
            $overallDeviationsDto = $this->calculateOverallDeviations($dashboardTotalsDto);
        }

        return [
            'periodData' => $periodDataCollection,
            'dashboardTotals' => $dashboardTotalsDto,
            'totalDeviationsDetails' => $overallDeviationsDto,
        ];
    }

    /**
     * Iterates through the date range, processing raw schedule, leave, attendance,
     * and worked data for each day into a structured format for the view.
     * This method is analogous to the original processPeriodData in UserDashboard.
     */
    protected function processPeriodData(array $data, Carbon $start, Carbon $end, bool $showDeviations, User $user): Collection
    {
        $dates = new Collection;
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $dateString = $cursor->toDateString();

            $scheduleDto = $this->processScheduleData($data['schedules'], $cursor);
            $leaveDto = $this->processLeaveData($data['leaves'], $dateString, $data['schedules']);
            $attendanceDto = $this->processAttendanceData($data['attendances'], $dateString);
            $workedDto = $this->processWorkedData($data['time_entries'], $dateString, $user);

            $dailyDeviationDetailsDto = $this->calculateDailyDeviations(
                $scheduleDto,
                $attendanceDto,
                $workedDto,
                $leaveDto,
                $showDeviations
            );

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

    /**
     * Processes schedule data for a specific day.
     * (Copied from UserDashboard and made protected)
     */
    protected function processScheduleData(Collection $schedules, Carbon $localDate): DailyScheduleData
    {
        $activeSchedule = $this->findActiveSchedule($schedules, $localDate->toDateString());

        if (! $activeSchedule || ! $activeSchedule->schedule) {
            return new DailyScheduleData(duration: '0h 0m', slots: [], scheduleName: null);
        }

        $weekday = ($localDate->dayOfWeek + 6) % 7;
        $details = $activeSchedule->schedule->scheduleDetails->where('weekday', $weekday);
        $targetHours = $activeSchedule->schedule->average_hours_day ?? 8.0;
        $targetMinutes = $targetHours * 60;

        if ($details->count() > 0) {
            $periodGroups = $details->groupBy('day_period');
            $selectedDetails = collect();
            foreach ($periodGroups as $periodDetails) {
                if ($periodDetails->count() == 1) {
                    $selectedDetails->push($periodDetails->first());
                } else {
                    $standardPeriodMins = 240;
                    $closestDetail = $periodDetails
                        ->sortBy(function ($detail) use ($standardPeriodMins) {
                            $start = Carbon::parse($detail->start);
                            $end = Carbon::parse($detail->end);
                            $mins = $start->diffInMinutes($end);

                            return abs($mins - $standardPeriodMins);
                        })
                        ->first();
                    $selectedDetails->push($closestDetail);
                }
            }
            $selectedDetails = $selectedDetails->sortBy('start');
        } else {
            $selectedDetails = $details->sortBy('start');
        }

        $totalMinutes = 0;
        $slots = [];
        foreach ($selectedDetails as $detail) {
            $start = Carbon::parse($detail->start)->setTimezone('UTC');
            $end = Carbon::parse($detail->end)->setTimezone('UTC');
            $minutesForSlot = $start->diffInMinutes($end);
            $totalMinutes += $minutesForSlot;
            $slots[] = ucfirst($detail->day_period).": {$start->format('H:i')} - {$end->format('H:i')}";
        }

        return new DailyScheduleData(
            duration: $this->formatMinutesToHoursMinutes($totalMinutes),
            slots: $slots,
            scheduleName: $activeSchedule->schedule->description ?? null,
        );
    }

    /**
     * Processes leave data for a specific day.
     * (Copied from UserDashboard and made protected)
     */
    protected function processLeaveData(Collection $leaves, string $dateString, ?Collection $schedules = null): ?DailyLeaveData
    {
        $leave = $this->findActiveLeave($leaves, $dateString);
        if (! $leave) {
            return null;
        }

        $contextInfo = match ($leave->type) {
            'department' => $leave->department->name ?? '',
            'category' => $leave->category->name ?? '',
            default => '',
        };

        $startTime = $leave->start_date->format('H:i');
        $endTime = $leave->end_date->format('H:i');
        $timeRange = "{$startTime} - {$endTime}";
        $halfDayTime = $leave->getFormattedHalfDayHours();
        $durationMinutes = 0;
        $dateCarbon = Carbon::parse($dateString);
        $isMultiDayLeave = ! $leave->start_date->isSameDay($leave->end_date) || $leave->duration_days > 1;
        $isWeekend = $dateCarbon->isWeekend();

        if ($leave->start_date->isSameDay($leave->end_date) && $leave->duration_days < 1) {
            $durationMinutes = $leave->start_date->diffInMinutes($leave->end_date);
            if ($durationMinutes == 0 && $leave->duration_days > 0 && $leave->status === 'validate') {
                if ($schedules !== null) {
                    $scheduledMinutesForDay = $this->getScheduledDurationForDate($schedules, $dateString);
                    if ($scheduledMinutesForDay > 0) {
                        $durationMinutes = (int) round($leave->duration_days * $scheduledMinutesForDay);
                    } elseif (! $isWeekend) {
                        $durationMinutes = (int) round($leave->duration_days * 8 * 60);
                    }
                } elseif (! $isWeekend) {
                    $durationMinutes = (int) round($leave->duration_days * 8 * 60);
                }
            }
        } else {
            if ($schedules !== null) {
                $scheduledMinutes = $this->getScheduledDurationForDate($schedules, $dateString);
                if ($scheduledMinutes == 0 && ! $isWeekend) {
                    $scheduledMinutes = 8 * 60;
                }
                $durationMinutes = $scheduledMinutes;
            } else {
                if (! $isWeekend) {
                    $durationMinutes = 8 * 60;
                } else {
                    $durationMinutes = 0;
                }
            }
        }

        if ($leave->status === 'validate' && $durationMinutes == 0 && ! $isWeekend && $leave->duration_days > 0) {
            if ($leave->duration_days >= 1) {
                $durationMinutes = 8 * 60;
            } elseif ($leave->duration_days > 0 && $leave->duration_days < 1) {
                $durationMinutes = (int) round($leave->duration_days * 8 * 60);
            }
        }

        if ($leave->status === 'validate' && $isWeekend && $durationMinutes == 0 && $leave->duration_days > 0) {
            if ($leave->duration_days >= 1) {
                $durationMinutes = 8 * 60;
            } else {
                $durationMinutes = (int) round($leave->duration_days * 8 * 60);
            }
        }

        $durationText = '';
        if ($leave->duration_days == 0.5) {
            $timeInfo = $leave->isMorningLeave() ? 'Morning' : ($leave->isAfternoonLeave() ? 'Afternoon' : '');
            $durationText = 'Half day'.($timeInfo ? " ($timeInfo)" : '');
        } elseif ($leave->duration_days == 1) {
            $durationText = '1 day';
        } else {
            $durationText = CarbonInterval::days($leave->duration_days)->cascade()->forHumans(['parts' => 2]);
        }

        return new DailyLeaveData(
            type: $leave->type,
            context: $contextInfo,
            leaveType: $leave->leaveType->name ?? '[No Type Set]',
            duration: $durationText,
            durationHours: $this->formatMinutesToHoursMinutes($durationMinutes),
            durationDays: $leave->duration_days,
            status: $leave->status ?? 'validate',
            isHalfDay: $leave->isHalfDay(),
            timePeriod: $leave->isMorningLeave() ? 'morning' : ($leave->isAfternoonLeave() ? 'afternoon' : 'full-day'),
            timeRange: $timeRange,
            halfDayTime: $halfDayTime,
            startTime: $startTime,
            endTime: $endTime,
            actualMinutes: (int) $durationMinutes,
            leaveTypeDescription: $leave->leaveType?->description,
        );
    }

    /**
     * Processes attendance data for a specific day.
     * (Copied from UserDashboard and made protected)
     */
    protected function processAttendanceData(Collection $attendances, string $dateString): DailyAttendanceData
    {
        $targetDate = Carbon::parse($dateString)->startOfDay();
        $attendance = $attendances
            ->filter(function ($record) use ($targetDate) {
                if (! $record->date) {
                    return false;
                }
                $recordDate = $record->date instanceof Carbon ? $record->date : Carbon::parse($record->date);

                return $recordDate->startOfDay()->equalTo($targetDate);
            })
            ->first();

        if (! $attendance) {
            return new DailyAttendanceData(duration: '0h 0m', isRemote: false, times: []);
        }

        $isRemote = (bool) $attendance->is_remote;
        $durationMinutes = 0;
        $times = [];

        if ($isRemote) {
            $durationMinutes = $attendance->presence_seconds / 60;
        } else {
            if ($attendance->start && $attendance->end) {
                $start = Carbon::parse($attendance->start);
                $end = Carbon::parse($attendance->end);
                $durationMinutes = $start->diffInMinutes($end);
                $times = [$start->format('H:i'), $end->format('H:i')];
            } else {
                $durationMinutes = $attendance->presence_seconds / 60;
            }
        }

        return new DailyAttendanceData(
            duration: $this->formatMinutesToHoursMinutes((int) $durationMinutes),
            isRemote: $isRemote,
            times: $times,
        );
    }

    /**
     * Processes worked data (time entries) for a specific day.
     * (Copied from UserDashboard and made protected, added User parameter for logging)
     */
    protected function processWorkedData(Collection $timeEntries, string $dateString, User $user): DailyWorkedData
    {
        $defaultStructure = ['duration' => '0h 0m', 'projects' => [], 'detailed_entries' => []];
        $targetDate = Carbon::parse($dateString)->startOfDay();
        $filtered = $timeEntries->filter(function ($entry) use ($targetDate) {
            if (! $entry->date) {
                return false;
            }
            $entryDate = $entry->date instanceof Carbon ? $entry->date : Carbon::parse($entry->date);

            return $entryDate->startOfDay()->equalTo($targetDate);
        });

        if ($filtered->isEmpty()) {
            return new DailyWorkedData(duration: '0h 0m', projects: new Collection, detailedEntries: new Collection);
        }

        try {
            $totalMinutes = $filtered->sum(fn ($entry) => ($entry->duration_seconds ?? 0) / 60);
            $projects = $filtered
                ->groupBy(fn ($entry) => data_get($entry, 'project.name', 'Unknown Project'))
                ->map(function ($group, $projectName) {
                    return new ProjectTaskSummaryData(
                        name: $projectName,
                        tasks: $group->pluck('task.name')->filter()->unique()->values(),
                    );
                })
                ->values()->all();
            $detailedEntries = $filtered
                ->map(function ($entry) {
                    $minutes = ($entry->duration_seconds ?? 0) / 60;

                    return new WorkedTimeEntry(
                        project: data_get($entry, 'project.name', 'Unknown Project'),
                        task: data_get($entry, 'task.name'),
                        description: $entry->description ?? '',
                        duration: $this->formatMinutesToHoursMinutes($minutes),
                        status: $entry->status ?? 'none',
                    );
                })
                ->values()->all();

            return new DailyWorkedData(
                duration: $this->formatMinutesToHoursMinutes($totalMinutes),
                projects: new Collection($projects),
                detailedEntries: new Collection($detailedEntries),
            );
        } catch (\Exception $e) {
            Log::error('Error processing worked data', [
                'exception' => $e->getMessage(),
                'date' => $dateString,
                'user_id' => $user->id, // Use passed $user
            ]);

            return new DailyWorkedData(duration: '0h 0m', projects: new Collection, detailedEntries: new Collection);
        }
    }

    /**
     * Finds the user's active schedule record for a specific date.
     * (Copied from UserDashboard and made protected)
     */
    protected function findActiveSchedule(Collection $schedules, string $dateString): ?object
    {
        $dateCarbon = Carbon::parse($dateString);

        return $schedules->first(function ($schedule) use ($dateCarbon) {
            if (! $schedule->effective_from) {
                return false;
            }
            $from = Carbon::parse($schedule->effective_from);
            $until = $schedule->effective_until ? Carbon::parse($schedule->effective_until) : null;

            return $from->lte($dateCarbon) && (! $until || $until->gte($dateCarbon));
        });
    }

    /**
     * Finds the user's active leave record for a specific date.
     * (Copied from UserDashboard and made protected)
     */
    protected function findActiveLeave(Collection $leaves, string $dateString): ?object
    {
        $dayCarbon = Carbon::parse($dateString)->startOfDay();

        return $leaves->first(function ($leave) use ($dayCarbon) {
            $leaveStartDate = $leave->start_date->copy()->startOfDay();
            $leaveEndDate = $leave->end_date->copy()->startOfDay();

            return $leaveStartDate->lte($dayCarbon) && $leaveEndDate->gte($dayCarbon);
        });
    }

    /**
     * Helper to retrieve the total scheduled duration (in minutes) for a specific date.
     * (Copied from UserDashboard and made protected)
     */
    protected function getScheduledDurationForDate(Collection $schedules, string $dateString): int
    {
        $localDate = Carbon::parse($dateString);
        $scheduleDto = $this->processScheduleData($schedules, $localDate);

        return $this->durationToMinutes($scheduleDto->duration);
    }

    /**
     * Calculates deviation details for a single day.
     * (Copied from UserDashboard, made protected, takes showDeviations parameter)
     * This method now takes the base day data and enriches it with deviation details.
     */
    protected function calculateDailyDeviations(
        DailyScheduleData $scheduleDto,
        DailyAttendanceData $attendanceDto,
        DailyWorkedData $workedDto,
        ?DailyLeaveData $leaveDto,
        bool $showDeviations
    ): ?DailyDeviationDetails {
        if (! $showDeviations) {
            // Return a DailyDeviationDetails with all values indicating hidden or zero, if preferred
            // For simplicity, returning null if not shown, let view handle this.
            // Or, create a 'hidden' state DTO if needed by the view always.
            $hiddenDetail = new DeviationDetail(0, 0, 'Deviations hidden', false);

            return new DailyDeviationDetails($hiddenDetail, $hiddenDetail, $hiddenDetail);
        }

        $scheduledMinutes = $this->durationToMinutes($scheduleDto->duration);
        $attendanceMinutes = $this->durationToMinutes($attendanceDto->duration);
        $workedMinutes = $this->durationToMinutes($workedDto->duration);
        $leaveActualMinutes = 0;
        $isRemoteWorkLeave = false;

        if ($leaveDto && $leaveDto->status === 'validate') {
            $leaveActualMinutes = $leaveDto->actualMinutes;
            if (Str::contains($leaveDto->type, 'Horas Teletrabajo')) {
                $isRemoteWorkLeave = true;
            }
        }

        $leaveMinutesToSubtract = $leaveActualMinutes > 0 && ! $isRemoteWorkLeave ? $leaveActualMinutes : 0;
        $effectiveScheduledMinutes = max(0, $scheduledMinutes - $leaveMinutesToSubtract);

        // Attendance vs Scheduled
        $diffAttVsSch = $attendanceMinutes - $effectiveScheduledMinutes;
        $percAttVsSch = 0;
        if ($effectiveScheduledMinutes > 0) {
            $percAttVsSch = round(($diffAttVsSch / $effectiveScheduledMinutes) * 100);
        } elseif ($attendanceMinutes > 0) {
            $percAttVsSch = 100;
        }
        $attVsSchDetail = new DeviationDetail(
            percentage: (int) $percAttVsSch,
            differenceMinutes: $diffAttVsSch,
            tooltip: $this->formatDeviationTooltip($diffAttVsSch, 'attendance than scheduled'),
            shouldDisplay: $effectiveScheduledMinutes > 0 || $attendanceMinutes > 0
        );

        // Worked vs Scheduled
        $diffWorkVsSch = $workedMinutes - $effectiveScheduledMinutes;
        $percWorkVsSch = 0;
        if ($effectiveScheduledMinutes > 0) {
            $percWorkVsSch = round(($diffWorkVsSch / $effectiveScheduledMinutes) * 100);
        } elseif ($workedMinutes > 0) {
            $percWorkVsSch = 100;
        }
        $workVsSchDetail = new DeviationDetail(
            percentage: (int) $percWorkVsSch,
            differenceMinutes: $diffWorkVsSch,
            tooltip: $this->formatDeviationTooltip($diffWorkVsSch, 'worked than scheduled'),
            shouldDisplay: $effectiveScheduledMinutes > 0 || $workedMinutes > 0
        );

        // Worked vs Attendance
        $diffWorkVsAtt = $workedMinutes - $attendanceMinutes;
        $percWorkVsAtt = 0;
        if ($attendanceMinutes > 0) {
            $percWorkVsAtt = round(($diffWorkVsAtt / $attendanceMinutes) * 100);
        } elseif ($workedMinutes > 0) {
            $percWorkVsAtt = 100;
        }
        $workVsAttDetail = new DeviationDetail(
            percentage: (int) $percWorkVsAtt,
            differenceMinutes: $diffWorkVsAtt,
            tooltip: $this->formatDeviationTooltip($diffWorkVsAtt, 'worked than attendance'),
            shouldDisplay: $attendanceMinutes > 0 || $workedMinutes > 0
        );

        return new DailyDeviationDetails($attVsSchDetail, $workVsSchDetail, $workVsAttDetail);
    }

    /**
     * Calculates aggregate totals (in minutes) for the period.
     * (Logic from UserDashboard::getTotals computed property)
     */
    protected function calculatePeriodTotals(Collection $periodDataCollection): DashboardTotals
    {
        $totals = ['scheduled' => 0, 'attendance' => 0, 'worked' => 0, 'leave' => 0];

        foreach ($periodDataCollection as $dayDto) {
            $dayDate = Carbon::parse($dayDto->date);
            if ($dayDate->isFuture()) {
                continue;
            }

            $totals['scheduled'] += $this->durationToMinutes($dayDto->scheduled->duration);
            $totals['attendance'] += $this->durationToMinutes($dayDto->attendance->duration);
            $totals['worked'] += $this->durationToMinutes($dayDto->worked->duration);

            if ($dayDto->leave && $dayDto->leave->status === 'validate') {
                if (! Str::contains($dayDto->leave->type, 'Horas Teletrabajo')) {
                    $totals['leave'] += $dayDto->leave->actualMinutes;
                }
            }
        }

        return new DashboardTotals(
            scheduled: $totals['scheduled'],
            attendance: $totals['attendance'],
            worked: $totals['worked'],
            leave: $totals['leave']
        );
    }

    /**
     * Calculates total deviation details for the entire period.
     * (Logic from UserDashboard::totalDeviations computed property)
     */
    protected function calculateOverallDeviations(DashboardTotals $dashboardTotalsDto): ?OverallDeviationDetails
    {
        $effectiveScheduledMinutes = max(0, $dashboardTotalsDto->scheduled - $dashboardTotalsDto->leave);

        // Att vs Sch
        $diffAttVsSch = $dashboardTotalsDto->attendance - $effectiveScheduledMinutes;
        $percAttVsSch = 0;
        if ($effectiveScheduledMinutes > 0) {
            $percAttVsSch = round(($diffAttVsSch / $effectiveScheduledMinutes) * 100);
        } elseif ($dashboardTotalsDto->attendance > 0) {
            $percAttVsSch = 100;
        }
        $attVsSchDetail = new DeviationDetail(
            percentage: (int) $percAttVsSch,
            differenceMinutes: $diffAttVsSch,
            tooltip: $this->formatDeviationTooltip($diffAttVsSch, 'attendance than scheduled'),
            shouldDisplay: $effectiveScheduledMinutes > 0 || $dashboardTotalsDto->attendance > 0
        );

        // Worked vs Sch
        $diffWorkVsSch = $dashboardTotalsDto->worked - $effectiveScheduledMinutes;
        $percWorkVsSch = 0;
        if ($effectiveScheduledMinutes > 0) {
            $percWorkVsSch = round(($diffWorkVsSch / $effectiveScheduledMinutes) * 100);
        } elseif ($dashboardTotalsDto->worked > 0) {
            $percWorkVsSch = 100;
        }
        $workVsSchDetail = new DeviationDetail(
            percentage: (int) $percWorkVsSch,
            differenceMinutes: $diffWorkVsSch,
            tooltip: $this->formatDeviationTooltip($diffWorkVsSch, 'worked than scheduled'),
            shouldDisplay: $effectiveScheduledMinutes > 0 || $dashboardTotalsDto->worked > 0
        );

        // Worked vs Att
        $diffWorkVsAtt = $dashboardTotalsDto->worked - $dashboardTotalsDto->attendance;
        $percWorkVsAtt = 0;
        if ($dashboardTotalsDto->attendance > 0) {
            $percWorkVsAtt = round(($diffWorkVsAtt / $dashboardTotalsDto->attendance) * 100);
        } elseif ($dashboardTotalsDto->worked > 0) {
            $percWorkVsAtt = 100;
        }
        $workVsAttDetail = new DeviationDetail(
            percentage: (int) $percWorkVsAtt,
            differenceMinutes: $diffWorkVsAtt,
            tooltip: $this->formatDeviationTooltip($diffWorkVsAtt, 'worked than attendance'),
            shouldDisplay: $dashboardTotalsDto->attendance > 0 || $dashboardTotalsDto->worked > 0
        );

        return new OverallDeviationDetails($attVsSchDetail, $workVsSchDetail, $workVsAttDetail);
    }

    private function formatDeviationTooltip(int $diffMinutes, string $comparisonText): string
    {
        if ($diffMinutes === 0) {
            return 'No difference';
        }
        $formattedDiff = $this->formatMinutesToHoursMinutes(abs($diffMinutes));
        $direction = $diffMinutes > 0 ? 'more' : 'less';

        return sprintf('%s %s %s', $formattedDiff, $direction, $comparisonText);
    }
}
