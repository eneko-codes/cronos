<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Exceptions\DataTransferObjectException;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\UserAttendance;
use App\Models\UserLeave;
use App\Models\UserSchedule;
use App\Services\Dashboard\ScheduleService;
use App\Services\DurationFormatterService;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Lazy]
class UserTimeSheetTable extends Component
{
    #[Locked]
    public int $userId;

    /**
     * The first date of the currently displayed period (YYYY-MM-DD string).
     */
    #[Url]
    public string $currentDate;

    /**
     * Current view mode: 'weekly' or 'monthly'.
     */
    #[Url]
    public string $viewMode = 'weekly';

    /**
     * Flag to toggle the display of deviation percentage columns.
     */
    #[Url]
    public bool $showDeviations = false;

    /**
     * The period data for the user.
     *
     * @var Collection<string, array>
     */
    public Collection $periodData;

    /**
     * The dashboard totals for the user.
     */
    public ?array $dashboardTotals = null;

    /**
     * The total deviations details for the user.
     */
    public ?array $totalDeviationsDetails = null;

    /**
     * Mount the component.
     *
     * @param  int  $userId  The user ID to mount the component for.
     */
    public function mount(int $userId): void
    {
        $this->userId = $userId;
        $this->currentDate = now()->toDateString();
        // viewMode and showDeviations will be initialized from URL or their defaults
        $this->loadPeriodDataAndTotals();
    }

    /**
     * Get the user for the component.
     */
    #[Computed]
    public function user(): User
    {
        // Uses getDataForDateRange() which handles its own eager loading
        return User::findOrFail($this->userId);
    }

    /**
     * Toggles the visibility of the deviation columns and reloads data.
     */
    public function toggleDeviations(): void
    {
        $this->showDeviations = ! $this->showDeviations;
        $this->loadPeriodDataAndTotals();
    }

    /**
     * Switches the view between 'weekly' and 'monthly' modes and reloads data.
     *
     * @param  string  $mode  The desired view mode ('weekly' or 'monthly').
     */
    public function changeViewMode(string $mode): void
    {
        $this->viewMode = $mode;
        $this->loadPeriodDataAndTotals();
    }

    /**
     * Navigates the calendar view to the previous week or month.
     */
    public function previousPeriod(): void
    {
        $startDate = $this->getPeriodStart();
        $this->setPeriodStart(
            $this->viewMode === 'weekly' ? $startDate->subWeek() : $startDate->subMonth()
        );
        $this->loadPeriodDataAndTotals();
    }

    /**
     * Navigates the calendar view to the next week or month.
     */
    public function nextPeriod(): void
    {
        $startDate = $this->getPeriodStart();
        $this->setPeriodStart(
            $this->viewMode === 'weekly' ? $startDate->addWeek() : $startDate->addMonth()
        );
        $this->loadPeriodDataAndTotals();
    }

    /**
     * Computes whether the "next period" navigation button should be disabled.
     * Prevents navigating into the future.
     */
    #[Computed]
    public function isNextPeriodDisabled(): bool
    {
        $current = Carbon::parse($this->currentDate);
        if ($this->viewMode === 'weekly') {
            $candidate = $current->copy()->addWeek();

            return $candidate->startOf('week')->gt(now());
        } else {
            $candidate = $current->copy()->addMonth();

            return $candidate->startOf('month')->gt(now());
        }
    }

    /**
     * Gets the start date of the current period as a Carbon instance (UTC).
     */
    protected function getPeriodStart(): Carbon
    {
        return Carbon::parse($this->currentDate)->startOfDay();
    }

    /**
     * Fetches and processes user data for the current period using internal methods.
     */
    protected function loadPeriodDataAndTotals(): void
    {
        $startDate = $this->viewMode === 'weekly'
            ? Carbon::parse($this->currentDate)->startOfWeek()
            : Carbon::parse($this->currentDate)->startOfMonth();

        $endDate = $this->viewMode === 'weekly'
            ? Carbon::parse($this->currentDate)->endOfWeek()
            : Carbon::parse($this->currentDate)->endOfMonth();

        $data = $this->aggregatePeriodData(
            $this->user,
            $startDate,
            $endDate,
            $this->showDeviations
        );

        $this->periodData = $data['periodData'];
        $this->dashboardTotals = $data['dashboardTotals'];
        $this->totalDeviationsDetails = $data['totalDeviationsDetails'];
    }

    /**
     * Sets the start date of the current period from a Carbon instance.
     *
     * @param  Carbon  $date  The Carbon date object to set as the start of the period.
     */
    protected function setPeriodStart(Carbon $date): void
    {
        $this->currentDate = $date->format('Y-m-d');
    }

    public function getFormattedTotalDuration(int $minutes): string
    {
        if ($minutes <= 0) {
            return '';
        }

        // Manual calculation to handle hours > 24 correctly
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return "{$hours}h {$remainingMinutes}m";
    }

    public function dispatchPreviousPeriod(): void
    {
        $this->previousPeriod();
    }

    public function dispatchNextPeriod(): void
    {
        $this->nextPeriod();
    }

    public function dispatchToggleDeviations(): void
    {
        $this->toggleDeviations();
    }

    public function dispatchChangeViewMode(string $mode): void
    {
        $this->changeViewMode($mode);
    }

    public function placeholder(array $params = [])
    {
        // The placeholder view can use $params['viewMode'] if needed to adjust skeleton rows/cols
        // For now, a generic table skeleton.
        return view('livewire.dashboard.user-time-sheet-table-skeleton', $params);
    }

    /**
     * Calculate daily deviations between schedule, attendance, and worked time.
     * Only calculates deviations for past dates and today - future dates return empty deviations.
     *
     * @param  mixed  $schedule  The schedule data for the day
     * @param  mixed  $attendance  The attendance data for the day
     * @param  mixed  $worked  The worked time data for the day
     * @param  mixed|null  $leave  The leave data for the day
     * @param  string|null  $dateString  The date string to check if it's in the future
     * @return array The calculated deviations
     */
    public function calculateDailyDeviations(
        $schedule,
        $attendance,
        $worked,
        $leave = null,
        $dateString = null
    ): array {
        // If date is provided and it's in the future, return empty deviations
        if ($dateString && Carbon::parse($dateString)->startOfDay()->isFuture()) {
            return [
                'attendanceVsScheduled' => [
                    'percentage' => 0,
                    'differenceMinutes' => 0,
                    'tooltip' => '',
                    'shouldDisplay' => false,
                ],
                'workedVsScheduled' => [
                    'percentage' => 0,
                    'differenceMinutes' => 0,
                    'tooltip' => '',
                    'shouldDisplay' => false,
                ],
                'workedVsAttendance' => [
                    'percentage' => 0,
                    'differenceMinutes' => 0,
                    'tooltip' => '',
                    'shouldDisplay' => false,
                ],
            ];
        }

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
        $formattedTime = DurationFormatterService::fromMinutes(abs($difference));
        $sign = $isPositive ? '+' : '-';

        return $sign.$formattedTime;
    }

    /**
     * Calculate totals from period data.
     * Only includes past dates and today - future dates are excluded.
     *
     * @param  Collection<string, array>  $periodData  The period data to calculate totals from
     */
    public function calculateTotals(Collection $periodData): array
    {
        $totals = [
            'scheduled' => 0,
            'attendance' => 0,
            'worked' => 0,
            'leave' => 0,
        ];

        $today = now()->startOfDay();

        foreach ($periodData as $dayData) {
            // Skip total rows that may be included in the data
            if (isset($dayData['isTotalRow']) && $dayData['isTotalRow']) {
                continue;
            }

            // Skip future dates - only include past dates and today
            $dayDate = Carbon::parse($dayData['date'])->startOfDay();
            if ($dayDate->isFuture()) {
                continue;
            }

            $totals['scheduled'] += $this->timeToMinutes($dayData['scheduled']['duration'] ?? '');
            $totals['attendance'] += $this->timeToMinutes($dayData['attendance']['duration'] ?? '');
            $totals['worked'] += $this->timeToMinutes($dayData['worked']['duration'] ?? '');
            // Use actual minutes from leave processing, not text duration
            $totals['leave'] += (int) ($dayData['leave']['actualMinutes'] ?? 0);
        }

        return $totals;
    }

    /**
     * Convert time string to minutes.
     *
     * @param  string  $time  Time string in format "Xh Ym" or empty string
     */
    protected function timeToMinutes(string $time): int
    {
        if (empty(trim($time))) {
            return 0;
        }

        // Try to parse using CarbonInterval first for consistency
        try {
            return (int) CarbonInterval::fromString($time)->totalMinutes;
        } catch (\Exception $e) {
            // Fallback to manual parsing for older format strings
            $parts = explode(' ', trim($time));
            $hours = 0;
            $minutes = 0;

            foreach ($parts as $part) {
                $part = trim($part);
                if (str_ends_with($part, 'h')) {
                    $hours = (int) rtrim($part, 'h');
                } elseif (str_ends_with($part, 'm')) {
                    $minutes = (int) rtrim($part, 'm');
                }
            }

            return ($hours * 60) + $minutes;
        }
    }

    /**
     * Format minutes into hours and minutes string.
     *
     * @param  int  $minutes  The total minutes
     */
    protected function formatMinutesToHoursMinutes(int $minutes): string
    {
        return DurationFormatterService::fromMinutes($minutes);
    }

    /**
     * Get formatted totals for display.
     *
     * @param  array  $totals  The calculated totals in minutes
     * @return array The formatted totals for display
     */
    public function getFormattedTotals(array $totals): array
    {
        return [
            'scheduled' => $this->formatMinutesToHoursMinutes($totals['scheduled']),
            'attendance' => $this->formatMinutesToHoursMinutes($totals['attendance']),
            'worked' => $this->formatMinutesToHoursMinutes($totals['worked']),
            'leave' => $this->formatMinutesToHoursMinutes($totals['leave']),
        ];
    }

    /**
     * Process leave data for a specific date.
     *
     * @param  Collection  $leaves  Collection of UserLeave models
     * @param  string  $dateString  The date to process leave for (Y-m-d)
     * @param  Collection|null  $schedules  Optional collection of schedules for duration calculation
     * @return array|null The processed leave data as an array, or null if no leave exists
     *
     * @throws DataTransferObjectException If there's an error processing the leave data
     */
    public function processLeaveData(Collection $leaves, string $dateString, ?Collection $schedules = null): ?array
    {
        try {
            $leave = $this->findActiveLeave($leaves, $dateString);
            if (! $leave) {
                return null;
            }

            $contextInfo = $this->getLeaveContext($leave);
            $timeInfo = $this->calculateLeaveTimeInfo($leave);
            $durationInfo = $this->calculateLeaveDurationInfo($leave, $dateString, $schedules);

            return [
                'model' => $leave,
                'type' => $leave->type,
                'context' => $contextInfo,
                'leaveType' => $leave->leaveType->name ?? '[No Type Set]',
                'duration' => $durationInfo['hours'], // Use actual hours format for consistency
                'durationText' => $durationInfo['text'], // Keep text version for display
                'durationDays' => $leave->duration_days,
                'status' => $leave->status ?? 'validate',
                'isHalfDay' => $leave->isHalfDay(),
                'timePeriod' => $leave->isMorningLeave() ? 'morning' : ($leave->isAfternoonLeave() ? 'afternoon' : 'full-day'),
                'timeRange' => $timeInfo['range'],
                'halfDayTime' => $timeInfo['halfDay'],
                'startTime' => $timeInfo['start'],
                'endTime' => $timeInfo['end'],
                'actualMinutes' => $durationInfo['minutes'],
                'leaveTypeDescription' => null,
            ];
        } catch (\Exception $e) {
            Log::error('Error processing leave data', [
                'date' => $dateString,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new DataTransferObjectException(
                "Failed to process leave data for date {$dateString}: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Find the active leave for a specific date.
     *
     * @param  Collection  $leaves  Collection of UserLeave models
     * @param  string  $dateString  The date to find leave for
     * @return UserLeave|null The active leave or null if none exists
     */
    protected function findActiveLeave(Collection $leaves, string $dateString): ?UserLeave
    {
        $dayCarbon = Carbon::parse($dateString)->startOfDay();

        return $leaves->first(function ($leave) use ($dayCarbon) {
            $leaveStartDate = $leave->start_date->copy()->startOfDay();
            $leaveEndDate = $leave->end_date->copy()->startOfDay();

            return $leaveStartDate->lte($dayCarbon) && $leaveEndDate->gte($dayCarbon);
        });
    }

    /**
     * Get the context information for a leave.
     *
     * @param  UserLeave  $leave  The leave model
     * @return string The context information
     */
    protected function getLeaveContext(UserLeave $leave): string
    {
        return match ($leave->type) {
            'department' => $leave->department->name ?? '',
            'category' => $leave->category->name ?? '',
            default => '',
        };
    }

    /**
     * Calculate time-related information for a leave.
     *
     * @param  UserLeave  $leave  The leave model
     * @return array{range: string, halfDay: string|null, start: string, end: string} Time information
     */
    protected function calculateLeaveTimeInfo(UserLeave $leave): array
    {
        $startTime = $leave->start_date->format('H:i');
        $endTime = $leave->end_date->format('H:i');

        return [
            'range' => "{$startTime} - {$endTime}",
            'halfDay' => $leave->getFormattedHalfDayHours(),
            'start' => $startTime,
            'end' => $endTime,
        ];
    }

    /**
     * Calculate duration information for a leave.
     *
     * @param  UserLeave  $leave  The leave model
     * @param  string  $dateString  The date string
     * @param  Collection|null  $schedules  Optional schedules collection
     * @return array{text: string, hours: string, minutes: int} Duration information
     */
    protected function calculateLeaveDurationInfo(UserLeave $leave, string $dateString, ?Collection $schedules = null): array
    {
        $dateCarbon = Carbon::parse($dateString);
        $durationText = UserLeave::formatDurationText($leave->duration_days);

        // For multi-day leaves, calculate the actual hours for this specific day based on schedule
        if (! $leave->start_date->isSameDay($leave->end_date)) {
            $durationMinutes = $this->calculateMultiDayActualMinutes($leave, $dateString, $schedules);
        } else {
            // Single day leave - Odoo's duration_days represents the actual time off
            // For full days: duration_days = 1.0 = full scheduled time
            // For partial days: duration_days = fraction of work day (e.g., 0.1875 = 1.5h out of 8h standard day)

            if ($schedules && $leave->duration_days >= 1.0) {
                // Full day leave - use actual scheduled time for that day
                $scheduleService = app(ScheduleService::class);
                $scheduledMinutes = $scheduleService->getScheduledDurationForDate($schedules, $dateString);
                $durationMinutes = $scheduledMinutes > 0 ? $scheduledMinutes : (8 * 60);
            } else {
                // Partial day leave - convert Odoo's days to minutes using standard 8-hour day
                // Odoo calculates duration_days based on standard work day, so we convert back
                $durationMinutes = (int) round($leave->duration_days * 8 * 60);
            }
        }

        return [
            'text' => $durationText,
            'hours' => DurationFormatterService::fromMinutes($durationMinutes),
            'minutes' => $durationMinutes,
        ];
    }

    /**
     * Calculate actual minutes for a specific day in a multi-day leave.
     */
    protected function calculateMultiDayActualMinutes(UserLeave $leave, string $dateString, ?Collection $schedules): int
    {
        if (! $schedules) {
            return 8 * 60; // fallback
        }

        // Get the scheduled minutes for this specific day
        $scheduleService = app(ScheduleService::class);
        $scheduledMinutes = $scheduleService->getScheduledDurationForDate($schedules, $dateString);

        // For multi-day leaves, we assume each day takes the full scheduled time for that day
        return $scheduledMinutes > 0 ? $scheduledMinutes : 8 * 60;
    }

    /**
     * Process attendance data for a specific date.
     *
     * @param  Collection  $attendances  Collection of UserAttendance models
     * @param  string  $dateString  The date to process attendance for (Y-m-d)
     * @return array|null The processed attendance data as an array, or null if no attendance exists
     */
    public function processAttendanceData(Collection $attendances, string $dateString): ?array
    {
        try {
            $dayAttendances = $this->findAttendancesForDate($attendances, $dateString);

            if ($dayAttendances->isEmpty()) {
                return null;
            }

            $durationInfo = $this->calculateAttendanceDurationInfo($dayAttendances);
            $times = $this->getAttendanceTimes($dayAttendances);
            $segments = $this->getSegments($dayAttendances);
            $workLocationInfo = $this->analyzeWorkLocation($dayAttendances);

            // Get overall start and end times
            $start = $dayAttendances->min('clock_in');
            $end = $dayAttendances->max('clock_out');

            // Determine if any segment is still active (has clock_in but no clock_out)
            $hasOpenSegment = $dayAttendances->contains(function ($attendance) {
                return (bool) ($attendance->clock_in && ! $attendance->clock_out);
            });

            return [
                'models' => $dayAttendances,
                'duration' => $durationInfo['formatted'],
                'is_remote' => $workLocationInfo['is_remote'],
                'is_mixed' => $workLocationInfo['is_mixed'],
                'has_office' => $workLocationInfo['has_office'],
                'has_remote' => $workLocationInfo['has_remote'],
                'times' => $times,
                'segments' => $segments,
                'has_open_segment' => $hasOpenSegment,
                'start' => $start ? Carbon::parse($start)->toDateTimeString() : null,
                'end' => $end ? Carbon::parse($end)->toDateTimeString() : null,
            ];
        } catch (\Exception $e) {
            Log::error('Error processing attendance data', [
                'date' => $dateString,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new DataTransferObjectException(
                "Failed to process attendance data for date {$dateString}: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Find all attendance records for a specific date.
     *
     * @param  Collection  $attendances  Collection of UserAttendance models
     * @param  string  $dateString  The date to find attendance for
     * @return Collection Collection of attendance records for the date
     */
    protected function findAttendancesForDate(Collection $attendances, string $dateString): Collection
    {
        $targetDate = Carbon::parse($dateString)->startOfDay();

        return $attendances->filter(function ($record) use ($targetDate) {
            if (! $record->date) {
                return false;
            }
            $recordDate = $record->date instanceof Carbon ? $record->date : Carbon::parse($record->date);

            return $recordDate->startOfDay()->equalTo($targetDate);
        })->sortBy('clock_in')->values();
    }

    /**
     * Calculate duration information for attendance records.
     *
     * @param  Collection  $attendances  Collection of attendance records for a day
     * @return array{minutes: int, formatted: string} Duration information
     */
    protected function calculateAttendanceDurationInfo(Collection $attendances): array
    {
        $totalSeconds = $attendances->sum('duration_seconds');

        if ($totalSeconds <= 0) {
            return [
                'minutes' => 0,
                'formatted' => '',
            ];
        }

        $interval = CarbonInterval::seconds((int) $totalSeconds)->cascade();

        return [
            'minutes' => (int) $interval->totalMinutes,
            'formatted' => $interval->format('%hh %Im'),
        ];
    }

    /**
     * Get attendance times for attendance records.
     *
     * @param  Collection  $attendances  Collection of attendance records for a day
     * @return array<string> Array of start and end times
     */
    protected function getAttendanceTimes(Collection $attendances): array
    {
        $firstClockIn = $attendances->whereNotNull('clock_in')->min('clock_in');
        $lastClockOut = $attendances->whereNotNull('clock_out')->max('clock_out');

        if ($firstClockIn && $lastClockOut) {
            return [
                Carbon::parse($firstClockIn)->setTimezone(config('app.timezone'))->format('H:i'),
                Carbon::parse($lastClockOut)->setTimezone(config('app.timezone'))->format('H:i'),
            ];
        }

        return [];
    }

    /**
     * Get individual segments for attendance records.
     *
     * @param  Collection  $attendances  Collection of attendance records for a day
     * @return array<array{clock_in: string|null, clock_out: string|null, duration: string}> Array of segments
     */
    protected function getSegments(Collection $attendances): array
    {
        return $attendances->map(function ($attendance) {
            return [
                'clock_in' => $attendance->clock_in ? Carbon::parse($attendance->clock_in)->setTimezone(config('app.timezone'))->format('H:i') : null,
                'clock_out' => $attendance->clock_out ? Carbon::parse($attendance->clock_out)->setTimezone(config('app.timezone'))->format('H:i') : null,
                'duration' => $attendance->formatted_duration,
            ];
        })->toArray();
    }

    /**
     * Analyze work location patterns for a day's attendance records.
     *
     * @param  Collection  $attendances  Collection of attendance records for a day
     * @return array{is_remote: bool, is_mixed: bool, has_office: bool, has_remote: bool} Work location analysis
     */
    protected function analyzeWorkLocation(Collection $attendances): array
    {
        $remoteRecords = $attendances->where('is_remote', true);
        $officeRecords = $attendances->where('is_remote', false);

        $hasRemote = $remoteRecords->isNotEmpty();
        $hasOffice = $officeRecords->isNotEmpty();
        $isMixed = $hasRemote && $hasOffice;

        // For backward compatibility, determine primary work type
        // If mixed, prioritize the type with more total duration
        $isRemote = false;
        if ($isMixed) {
            $remoteDuration = $remoteRecords->sum('duration_seconds');
            $officeDuration = $officeRecords->sum('duration_seconds');
            $isRemote = $remoteDuration >= $officeDuration;
        } elseif ($hasRemote) {
            $isRemote = true;
        }

        return [
            'is_remote' => $isRemote,
            'is_mixed' => $isMixed,
            'has_office' => $hasOffice,
            'has_remote' => $hasRemote,
        ];
    }

    /**
     * Process schedule data for a specific date.
     *
     * @param  Collection  $schedules  Collection of UserSchedule models
     * @param  string  $dateString  The date to process schedule for (Y-m-d)
     * @return array|null The processed schedule data as an array, or null if no schedule exists
     */
    public function processScheduleData(Collection $schedules, string $dateString): ?array
    {
        try {
            $schedule = $this->findScheduleForDate($schedules, $dateString);

            if (! $schedule) {
                return null;
            }

            $weekday = (Carbon::parse($dateString)->dayOfWeek + 6) % 7;
            $targetDate = Carbon::parse($dateString)->toDateString();

            // Filter schedule details to only include explicitly active ones for the target date
            $details = $schedule->schedule->scheduleDetails
                ->where('weekday', $weekday)
                ->filter(function ($detail) use ($targetDate) {
                    // Check if the detail is active
                    if ($detail->active !== true) {
                        return false;
                    }

                    // Check if the detail applies to the target date
                    $dateFrom = $detail->date_from ? $detail->date_from->toDateString() : null;
                    $dateTo = $detail->date_to ? $detail->date_to->toDateString() : null;

                    // If no date range specified, it applies to all dates
                    if (! $dateFrom && ! $dateTo) {
                        return true;
                    }

                    // Check if target date is within the range
                    $afterStart = ! $dateFrom || $targetDate >= $dateFrom;
                    $beforeEnd = ! $dateTo || $targetDate <= $dateTo;

                    return $afterStart && $beforeEnd;
                });

            $selectedDetails = $details->sortBy('start');

            $totalMinutes = 0;
            $slots = [];
            foreach ($selectedDetails as $detail) {
                // Get the raw time values from the database to avoid timezone conversion
                // The times are stored as time(0) without timezone in the database
                // but Laravel's datetime cast applies app timezone conversion
                // We access the original attributes to get the raw time strings
                $startTime = $detail->getAttributes()['start'] ?? $detail->start->format('H:i:s');
                $endTime = $detail->getAttributes()['end'] ?? $detail->end->format('H:i:s');

                // Parse as UTC times for duration calculation
                $start = Carbon::parse($startTime, 'UTC');
                $end = Carbon::parse($endTime, 'UTC');
                $minutesForSlot = $start->diffInMinutes($end);
                $totalMinutes += $minutesForSlot;
                $slots[] = "{$start->format('H:i')} - {$end->format('H:i')}";
            }

            // Return null for days with no schedule (0 minutes)
            if ($totalMinutes === 0) {
                return null;
            }

            return [
                'model' => $schedule,
                'duration' => DurationFormatterService::fromMinutes((int) round($totalMinutes)),
                'slots' => $slots,
                'scheduleName' => $schedule->schedule->description ?? null,
                'totalMinutes' => (int) round($totalMinutes), // Add this for leave calculation consistency
            ];
        } catch (\Exception $e) {
            Log::error('Error processing schedule data', [
                'date' => $dateString,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new DataTransferObjectException(
                "Failed to process schedule data for date {$dateString}: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Find schedule record for a specific date.
     *
     * @param  Collection  $schedules  Collection of UserSchedule models
     * @param  string  $dateString  The date to find schedule for
     * @return UserSchedule|null The schedule record or null if none exists
     */
    protected function findScheduleForDate(Collection $schedules, string $dateString): ?UserSchedule
    {
        $targetDate = Carbon::parse($dateString)->startOfDay();

        return $schedules->first(function ($record) use ($targetDate) {
            if (! $record->effective_from) {
                return false;
            }
            $from = Carbon::parse($record->effective_from);
            $until = $record->effective_until ? Carbon::parse($record->effective_until) : null;

            return $from->lte($targetDate) && (! $until || $until->gte($targetDate));
        });
    }

    /**
     * Process worked time data for a specific date.
     *
     * @param  string  $date  The date to process data for
     * @param  int  $userId  The user ID to process data for
     * @return array The processed worked time data as an array
     */
    public function processWorkedData(string $date, int $userId): array
    {
        try {
            $entries = $this->findEntriesForDate($date, $userId);
            $durationInfo = $this->calculateWorkedDurationInfo($entries);
            $projectSummaries = $this->generateProjectSummaries($entries);

            $detailedEntries = $entries->map(function (TimeEntry $entry): array {
                return [
                    'project' => optional($entry->project)->title ?? '—',
                    'task' => optional($entry->task)->name,
                    'description' => $entry->description,
                    'duration' => $entry->formatted_duration,
                ];
            });

            return [
                'entries' => $entries,
                'duration' => $durationInfo['formatted'],
                'projects' => collect($projectSummaries),
                'detailedEntries' => $detailedEntries,
            ];
        } catch (\Exception $e) {
            Log::error('Error processing worked data', [
                'date' => $date,
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new DataTransferObjectException(
                "Failed to process worked data for date {$date} and user {$userId}: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Find time entries for a specific date.
     *
     * @param  string  $date  The date to find entries for
     * @param  int  $userId  The user ID to find entries for
     * @return Collection<TimeEntry> The found time entries
     */
    protected function findEntriesForDate(string $date, int $userId): Collection
    {
        return TimeEntry::forUser($userId)
            ->forDate($date)
            ->with(['project', 'task'])
            ->get();
    }

    /**
     * Calculate duration information from time entries.
     *
     * @param  Collection<TimeEntry>  $entries  The time entries to calculate duration for
     * @return array{minutes: int, formatted: string} The duration information
     */
    protected function calculateWorkedDurationInfo(Collection $entries): array
    {
        $totalSeconds = $entries->sum(fn (TimeEntry $entry) => $entry->duration_seconds);

        // Return empty string for no worked time instead of "0h 00m"
        if ($totalSeconds <= 0 || $entries->isEmpty()) {
            return [
                'minutes' => 0,
                'formatted' => '',
            ];
        }

        $interval = CarbonInterval::seconds((int) $totalSeconds)->cascade();
        $formatted = $interval->format('%hh %Im');

        return [
            'minutes' => (int) $interval->totalMinutes,
            'formatted' => $formatted,
        ];
    }

    /**
     * Generate summaries of worked time by project.
     *
     * @param  Collection<TimeEntry>  $entries  The time entries to summarize
     * @return array<int, array{title: string, tasks: array<string>}>
     */
    protected function generateProjectSummaries(Collection $entries): array
    {
        $summaries = [];

        foreach ($entries->groupBy('proofhub_project_id') as $projectEntries) {
            $project = $projectEntries->first()->project;
            $uniqueTaskNames = $projectEntries
                ->pluck('task.name')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $summaries[] = [
                // Align key names with the Blade view expectations
                'title' => optional($project)->title ?? 'Unknown project',
                'tasks' => $uniqueTaskNames,
            ];
        }

        return $summaries;
    }

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
        $totals = $this->calculateTotals($periodData);
        $deviations = $showDeviations ? $this->calculateOverallDeviations($totals) : null;

        return [
            'periodData' => $periodData,
            'dashboardTotals' => $totals,
            'totalDeviationsDetails' => $deviations,
        ];
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

            $scheduleArr = $this->processScheduleData($data['schedules'], $dateString);
            $leaveArr = $this->processLeaveData($data['leaves'], $dateString, $data['schedules']);
            $attendanceArr = $this->processAttendanceData($data['attendances'], $dateString);
            $workedArr = $this->processWorkedData($dateString, $user->id);

            $dailyDeviationDetailsArr = $showDeviations
                ? $this->calculateDailyDeviations(
                    $scheduleArr,
                    $attendanceArr,
                    $workedArr,
                    $leaveArr ?? [],
                    $dateString
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

    public function render()
    {
        return view('livewire.dashboard.user-time-sheet-table');
    }
}
