<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Traits\FormatsDurationsTrait;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

// We will add models and other necessary classes as we move methods

class UserDashboardDataProcessorService
{
    use FormatsDurationsTrait;

    /**
     * Main public method to process period data for a user.
     *
     * @param  User  $user  The user for whom data is being processed.
     * @param  array  $rawData  Raw data collections ('schedules', 'leaves', 'attendances', 'time_entries').
     * @param  Carbon  $startDate  Start date of the period (UTC).
     * @param  Carbon  $endDate  End date of the period (UTC).
     * @param  bool  $showDeviations  Flag to indicate if deviation details should be calculated.
     * @return array Processed daily data keyed by date string.
     */
    public function getProcessedPeriodData(
        User $user,
        array $rawData,
        Carbon $startDate,
        Carbon $endDate,
        bool $showDeviations
    ): array {
        // Work only in UTC
        $dates = collect();
        $cursor = $startDate->copy();

        // Iterate through each day in the date range.
        while ($cursor->lte($endDate)) {
            $dateString = $cursor->toDateString();

            // Extract and process data subsets for the current day.
            $scheduleData = $this->processScheduleData($rawData['schedules'], $cursor);
            $leaveData = $this->processLeaveData(
                $rawData['leaves'],
                $dateString,
                $user, // Pass user for leave type description
                $rawData['schedules']
            );
            $attendanceData = $this->processAttendanceData(
                $rawData['attendances'],
                $dateString
            );
            $workedData = $this->processWorkedData(
                $rawData['time_entries'],
                $dateString,
                $user // Pass user for logging context
            );

            // Calculate daily deviations
            $dailyDeviationDetails = $this->calculateDailyDeviationsDetails(
                [
                    'scheduled' => $scheduleData,
                    'attendance' => $attendanceData,
                    'worked' => $workedData,
                    'leave' => $leaveData,
                ],
                $showDeviations
            );

            // Structure the data for the current day.
            $dates->put($dateString, [
                'date' => $dateString,
                'scheduled' => $scheduleData,
                'leave' => $leaveData,
                'attendance' => $attendanceData,
                'worked' => $workedData,
                'deviation_details' => $dailyDeviationDetails, // Use the calculated daily deviations
            ]);

            // Move to the next day.
            $cursor->addDay();
        }

        return $dates->all();
    }

    /**
     * Processes schedule data for a specific day.
     * (Copied from UserDashboard and made protected)
     */
    protected function processScheduleData(
        Collection $schedules,
        Carbon $localDate
    ): array {
        // Work only in UTC
        $activeSchedule = $this->findActiveSchedule(
            $schedules,
            $localDate->toDateString()
        );

        if (! $activeSchedule || ! $activeSchedule->schedule) {
            return ['duration' => '0h 0m', 'slots' => []];
        }

        $weekday = ($localDate->dayOfWeek + 6) % 7;

        $details = $activeSchedule->schedule->scheduleDetails->where(
            'weekday',
            $weekday
        );

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
            $slots[] =
              ucfirst($detail->day_period).
              ": {$start->format('H:i')} - {$end->format('H:i')}";
        }

        return [
            'duration' => $this->formatMinutesToHoursMinutes($totalMinutes),
            'slots' => $slots,
            'schedule_name' => $activeSchedule->schedule->description ?? null,
        ];
    }

    /**
     * Processes leave data for a specific day.
     * (Copied from UserDashboard and made protected, added User $user parameter)
     */
    protected function processLeaveData(
        Collection $leaves,
        string $dateString,
        User $user, // Added for context if needed, e.g., leave type description
        ?Collection $schedules = null
    ): ?array {
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
                $scheduledMinutes = $this->getScheduledDurationForDate(
                    $schedules,
                    $dateString
                );
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

        return [
            'type' => $leave->type,
            'context' => $contextInfo,
            'leave_type' => $leave->leaveType->name ?? '[No Type Set]',
            'duration' => $durationText,
            'duration_hours' => $this->formatMinutesToHoursMinutes($durationMinutes),
            'duration_days' => $leave->duration_days,
            'status' => $leave->status ?? 'validate',
            'is_half_day' => $leave->isHalfDay(),
            'time_period' => $leave->isMorningLeave() ? 'morning' : ($leave->isAfternoonLeave() ? 'afternoon' : 'full-day'),
            'time_range' => $timeRange,
            'half_day_time' => $halfDayTime,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'actual_minutes' => $durationMinutes,
            'leave_type_description' => $leave->leaveType?->description,
        ];
    }

    /**
     * Processes attendance data for a specific day.
     * (Copied from UserDashboard and made protected)
     */
    protected function processAttendanceData(
        Collection $attendances,
        string $dateString
    ): array {
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
            return ['duration' => '0h 0m', 'is_remote' => false, 'times' => []];
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

        return [
            'duration' => $this->formatMinutesToHoursMinutes((int) $durationMinutes),
            'is_remote' => $isRemote,
            'times' => $times,
        ];
    }

    /**
     * Processes worked data (ProofHub time entries) for a specific day.
     * (Copied from UserDashboard and made protected, added User $user for logging context)
     */
    protected function processWorkedData(
        Collection $timeEntries,
        string $dateString,
        User $user // Added for logging context
    ): array {
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
            return $defaultStructure;
        }

        try {
            $totalMinutes = $filtered->sum(fn ($entry) => ($entry->duration_seconds ?? 0) / 60);
            $projects = $filtered
                ->groupBy(fn ($entry) => data_get($entry, 'project.name', 'Unknown Project'))
                ->map(fn ($group, $projectName) => [
                    'name' => $projectName,
                    'tasks' => $group->pluck('task.name')->filter()->unique()->values()->all(),
                ])
                ->values()->all();
            $detailedEntries = $filtered
                ->map(function ($entry) {
                    $minutes = ($entry->duration_seconds ?? 0) / 60;

                    return [
                        'project' => data_get($entry, 'project.name', 'Unknown Project'),
                        'task' => data_get($entry, 'task.name'),
                        'description' => $entry->description ?? '',
                        'duration' => $this->formatMinutesToHoursMinutes($minutes),
                        'status' => $entry->status ?? 'none',
                    ];
                })
                ->values()->all();

            return [
                'duration' => $this->formatMinutesToHoursMinutes($totalMinutes),
                'projects' => $projects,
                'detailed_entries' => $detailedEntries,
            ];
        } catch (\Exception $e) {
            Log::error('Error processing worked data in service', [
                'exception' => $e->getMessage(),
                'date' => $dateString,
                'user_id' => $user->id, // Use passed user
            ]);

            return $defaultStructure;
        }
    }

    /**
     * Finds the user's active schedule record for a specific date.
     * (Copied from UserDashboard and made protected)
     */
    protected function findActiveSchedule(
        Collection $schedules,
        string $dateString
    ): ?object {
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
    protected function findActiveLeave(
        Collection $leaves,
        string $dateString
    ): ?object {
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
    protected function getScheduledDurationForDate(
        Collection $schedules,
        string $dateString
    ): int {
        $localDate = Carbon::parse($dateString);
        $scheduleData = $this->processScheduleData($schedules, $localDate);

        return $this->durationToMinutes($scheduleData['duration']);
    }

    /**
     * Calculates deviation details for a single day.
     * This method now takes $showDeviations as a parameter.
     * (Copied from UserDashboard's calculateDailyDeviations and renamed, made protected)
     */
    protected function calculateDailyDeviationsDetails(array $dayData, bool $showDeviations): array
    {
        $defaultDeviationStructure = [
            'attendance_vs_scheduled' => ['percentage' => 0, 'difference_minutes' => 0, 'tooltip' => 'Deviations hidden', 'class' => '', 'should_display' => false],
            'worked_vs_scheduled' => ['percentage' => 0, 'difference_minutes' => 0, 'tooltip' => 'Deviations hidden', 'class' => '', 'should_display' => false],
            'worked_vs_attendance' => ['percentage' => 0, 'difference_minutes' => 0, 'tooltip' => 'Deviations hidden', 'class' => '', 'should_display' => false],
        ];

        if (! $showDeviations) {
            return $defaultDeviationStructure;
        }

        $deviationDetails = [
            'attendance_vs_scheduled' => ['percentage' => 0, 'difference_minutes' => 0, 'tooltip' => '', 'class' => '', 'should_display' => false],
            'worked_vs_scheduled' => ['percentage' => 0, 'difference_minutes' => 0, 'tooltip' => '', 'class' => '', 'should_display' => false],
            'worked_vs_attendance' => ['percentage' => 0, 'difference_minutes' => 0, 'tooltip' => '', 'class' => '', 'should_display' => false],
        ];

        $scheduledMinutes = $this->durationToMinutes($dayData['scheduled']['duration']);
        $attendanceMinutes = $this->durationToMinutes($dayData['attendance']['duration']);
        $workedMinutes = $this->durationToMinutes($dayData['worked']['duration']);
        $leaveMinutes = isset($dayData['leave']['actual_minutes']) && $dayData['leave']['status'] === 'validate'
            ? $dayData['leave']['actual_minutes'] : 0;

        $isRemoteWorkLeave = false;
        if (is_array($dayData['leave']) && isset($dayData['leave']['type'])) {
            $isRemoteWorkLeave = Str::contains($dayData['leave']['type'], 'Horas Teletrabajo');
        }
        $leaveMinutesToSubtract = $leaveMinutes > 0 && ! $isRemoteWorkLeave ? $leaveMinutes : 0;
        $effectiveScheduledMinutes = max(0, $scheduledMinutes - $leaveMinutesToSubtract);

        $diffAttVsSch = $attendanceMinutes - $effectiveScheduledMinutes;
        $diffWorkVsSch = $workedMinutes - $effectiveScheduledMinutes;
        $diffWorkVsAtt = $workedMinutes - $attendanceMinutes;

        $deviationDetails['attendance_vs_scheduled']['difference_minutes'] = $diffAttVsSch;
        $deviationDetails['worked_vs_scheduled']['difference_minutes'] = $diffWorkVsSch;
        $deviationDetails['worked_vs_attendance']['difference_minutes'] = $diffWorkVsAtt;

        // Attendance vs Scheduled
        if ($effectiveScheduledMinutes > 0) {
            $deviationDetails['attendance_vs_scheduled']['percentage'] = round(($diffAttVsSch / $effectiveScheduledMinutes) * 100);
        } elseif ($attendanceMinutes > 0) {
            $deviationDetails['attendance_vs_scheduled']['percentage'] = 100;
        }
        // Worked vs Scheduled
        if ($effectiveScheduledMinutes > 0) {
            $deviationDetails['worked_vs_scheduled']['percentage'] = round(($diffWorkVsSch / $effectiveScheduledMinutes) * 100);
        } elseif ($workedMinutes > 0) {
            $deviationDetails['worked_vs_scheduled']['percentage'] = 100;
        }
        // Worked vs Attendance
        if ($attendanceMinutes > 0) {
            $deviationDetails['worked_vs_attendance']['percentage'] = round(($diffWorkVsAtt / $attendanceMinutes) * 100);
        } elseif ($workedMinutes > 0) {
            $deviationDetails['worked_vs_attendance']['percentage'] = 100;
        }

        $deviationDetails['attendance_vs_scheduled']['should_display'] = $effectiveScheduledMinutes > 0 || $attendanceMinutes > 0;
        $deviationDetails['worked_vs_scheduled']['should_display'] = $effectiveScheduledMinutes > 0 || $workedMinutes > 0;
        $deviationDetails['worked_vs_attendance']['should_display'] = $attendanceMinutes > 0 || $workedMinutes > 0;

        foreach ($deviationDetails as $deviation => &$details) { // Pass $details by reference
            $diffMinutes = $details['difference_minutes'];
            $formattedDiff = $this->formatMinutesToHoursMinutes(abs($diffMinutes));
            $comparisonText = match ($deviation) {
                'attendance_vs_scheduled' => 'attendance than scheduled',
                'worked_vs_scheduled' => 'worked than scheduled',
                'worked_vs_attendance' => 'worked than attendance',
            };
            $details['tooltip'] = 'No difference';
            if ($diffMinutes !== 0) {
                $direction = $diffMinutes > 0 ? 'more' : 'less';
                $details['tooltip'] = sprintf('%s %s %s', $formattedDiff, $direction, $comparisonText);
            }
        }
        unset($details); // Unset reference

        return $deviationDetails;
    }
}
