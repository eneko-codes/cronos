<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Setting;
use App\Models\User;
use App\Services\UserDataService;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('User Dashboard')]
class UserDashboard extends Component
{
    /**
     * The user model instance.
     */
    public User $user;

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
     * Holds the processed data for each day in the current period, structured for the Blade view.
     *
     * @var array<string, array{
     *   date: string,
     *   scheduled: array{duration: string, slots: array<string>, schedule_name?: string},
     *   leave?: array{
     *     type: string,
     *     duration: string,
     *     duration_hours: string,
     *     status: string,
     *     is_half_day: bool,
     *     time_period: string,
     *     half_day_time?: string,
     *     actual_minutes: int
     *   },
     *   attendance: array{duration: string, is_remote: bool, times: array<string>},
     *   worked: array{
     *     duration: string,
     *     projects: array<array{name: string, tasks: array<string>}>,
     *     detailed_entries: array<array{
     *       project: string,
     *       task?: string,
     *       description?: string,
     *       duration: string,
     *       status: string
     *     }>
     *   }
     * }>
     */
    protected array $periodData = [];

    /**
     * Flag indicating if the authenticated user has admin privileges.
     */
    public bool $isAdmin = false;

    /**
     * Flag indicating if we are viewing a specific user via ID, not the authenticated user's own dashboard.
     */
    public bool $isViewingSpecificUser = false;

    /**
     * Indicates if notifications are enabled globally.
     */
    public bool $isGloballyEnabled = true;

    // Inject service via constructor
    protected UserDataService $userDataService;

    public function boot(UserDataService $userDataService): void
    {
        $this->userDataService = $userDataService;
    }

    /**
     * Initializes the component, loads the user, sets the initial period, and loads data.
     *
     * @param  int|null  $id  The ID of the user to display, or null to display the authenticated user.
     */
    public function mount($id = null): void
    {
        $this->isViewingSpecificUser = $id !== null;

        if ($id !== null) {
            $this->user = User::with([
                'userSchedules.schedule.scheduleDetails',
                'userLeaves.leaveType',
                'userLeaves.department',
                'userLeaves.category',
                'userAttendances',
                'timeEntries.project',
                'timeEntries.task',
            ])->findOrFail($id);
        } else {
            $this->user = Auth::user()->load([
                'userSchedules.schedule.scheduleDetails',
                'userLeaves.leaveType',
                'userLeaves.department',
                'userLeaves.category',
                'userAttendances',
                'timeEntries.project',
                'timeEntries.task',
            ]);
        }

        // Check if the authenticated user is an admin
        $this->isAdmin = Auth::check() && Auth::user()->is_admin;

        // Always start with the current period based on view mode
        $this->setPeriodStart(
            $this->viewMode === 'weekly'
              ? now()->startOfWeek()
              : now()->startOfMonth()
        );

        // Load cache data for the current period
        // TODO: Implement caching strategy if performance becomes an issue.
        $this->loadPeriodDataAndTotals();

        // Load global notification setting
        $this->loadGlobalNotificationSetting();
    }

    /**
     * Toggles the visibility of the deviation columns and reloads data.
     */
    public function toggleDeviations(): void
    {
        $this->showDeviations = ! $this->showDeviations;
        // Reload data when the toggle changes to ensure deviation_details are populated/updated
        $this->loadPeriodDataAndTotals();
    }

    /**
     * Switches the view between 'weekly' and 'monthly' modes and reloads data.
     *
     * @param  string  $mode  The desired view mode ('weekly' or 'monthly').
     */
    public function changeViewMode(string $mode): void
    {
        if (! in_array($mode, ['weekly', 'monthly'])) {
            $this->viewMode = 'weekly'; // Reset to default

            return;
        }
        $this->viewMode = $mode;

        // Reset to the current week or month
        $this->setPeriodStart(
            $this->viewMode === 'weekly'
              ? now()->startOfWeek()
              : now()->startOfMonth()
        );

        // Reload data for the new period
        $this->loadPeriodDataAndTotals();
    }

    /**
     * Navigates the calendar view to the previous week or month.
     */
    public function previousPeriod(): void
    {
        $startDate = $this->getPeriodStart();

        if ($this->viewMode === 'weekly') {
            $this->setPeriodStart($startDate->subWeek());
        } else {
            $this->setPeriodStart($startDate->subMonth());
        }

        $this->loadPeriodDataAndTotals();
    }

    /**
     * Navigates the calendar view to the next week or month.
     */
    public function nextPeriod(): void
    {
        $startDate = $this->getPeriodStart();

        if ($this->viewMode === 'weekly') {
            $this->setPeriodStart($startDate->addWeek());
        } else {
            $this->setPeriodStart($startDate->addMonth());
        }

        $this->loadPeriodDataAndTotals();
    }

    /**
     * Calculates total deviation details (percentage, difference, tooltip) for the entire period.
     * Uses the aggregated totals calculated earlier.
     *
     * @return array<string, array{percentage: int, difference_minutes: int, tooltip: string, class: string, should_display: bool}> Deviation details.
     */
    #[Computed(persist: false)]
    public function totalDeviations(): array
    {
        $deviationDetails = [
            'attendance_vs_scheduled' => [
                'percentage' => 0,
                'difference_minutes' => 0,
                'tooltip' => '',
                'class' => '', // Class is handled in the Blade view
                'should_display' => false, // Flag to control rendering
            ],
            'worked_vs_scheduled' => [
                'percentage' => 0,
                'difference_minutes' => 0,
                'tooltip' => '',
                'class' => '', // Class is handled in the Blade view
                'should_display' => false, // Flag to control rendering
            ],
            'worked_vs_attendance' => [
                'percentage' => 0,
                'difference_minutes' => 0,
                'tooltip' => '',
                'class' => '', // Class is handled in the Blade view
                'should_display' => false, // Flag to control rendering
            ],
        ];

        // Get totals from the computed property
        $totals = $this->getTotals;

        // Calculate effective scheduled time by subtracting actual leave (excluding remote work leave)
        $effectiveScheduledMinutes = max(
            0,
            $totals['scheduled'] - $totals['leave']
        );

        // Calculate differences
        $diffAttVsSch = $totals['attendance'] - $effectiveScheduledMinutes;
        $diffWorkVsSch = $totals['worked'] - $effectiveScheduledMinutes;
        $diffWorkVsAtt = $totals['worked'] - $totals['attendance'];

        $deviationDetails['attendance_vs_scheduled'][
          'difference_minutes'
        ] = $diffAttVsSch;
        $deviationDetails['worked_vs_scheduled'][
          'difference_minutes'
        ] = $diffWorkVsSch;
        $deviationDetails['worked_vs_attendance'][
          'difference_minutes'
        ] = $diffWorkVsAtt;

        // --- Calculate Percentages ---

        // Attendance vs Scheduled (considering leave)
        if ($effectiveScheduledMinutes > 0) {
            $deviationDetails['attendance_vs_scheduled']['percentage'] = round(
                ($diffAttVsSch / $effectiveScheduledMinutes) * 100
            );
        } elseif ($totals['attendance'] > 0) {
            // Scheduled (after leave) is 0, but attendance exists
            $deviationDetails['attendance_vs_scheduled']['percentage'] = 100;
        } else {
            // Both are 0
            $deviationDetails['attendance_vs_scheduled']['percentage'] = 0;
        }

        // Worked vs Scheduled (considering leave)
        if ($effectiveScheduledMinutes > 0) {
            $deviationDetails['worked_vs_scheduled']['percentage'] = round(
                ($diffWorkVsSch / $effectiveScheduledMinutes) * 100
            );
        } elseif ($totals['worked'] > 0) {
            // Scheduled (after leave) is 0, but worked exists
            $deviationDetails['worked_vs_scheduled']['percentage'] = 100;
        } else {
            // Both are 0
            $deviationDetails['worked_vs_scheduled']['percentage'] = 0;
        }

        // Worked vs Attendance
        if ($totals['attendance'] > 0) {
            $deviationDetails['worked_vs_attendance']['percentage'] = round(
                ($diffWorkVsAtt / $totals['attendance']) * 100
            );
        } elseif ($totals['worked'] > 0) {
            // Attendance is 0, but worked exists
            $deviationDetails['worked_vs_attendance']['percentage'] = 100;
        } else {
            // Both are 0
            $deviationDetails['worked_vs_attendance']['percentage'] = 0;
        }

        // Determine if the total deviation percentages should be displayed
        $deviationDetails['attendance_vs_scheduled']['should_display'] =
          $effectiveScheduledMinutes > 0 || $totals['attendance'] > 0;
        $deviationDetails['worked_vs_scheduled']['should_display'] =
          $effectiveScheduledMinutes > 0 || $totals['worked'] > 0;
        $deviationDetails['worked_vs_attendance']['should_display'] =
          $totals['attendance'] > 0 || $totals['worked'] > 0;

        // Format tooltip text for each deviation type
        foreach ($deviationDetails as $deviation => $details) {
            $diffMinutes = $details['difference_minutes'];
            $formattedDiff = $this->formatMinutesToHoursMinutes(abs($diffMinutes));

            // Determine comparison text based on deviation key
            $comparisonText = match ($deviation) {
                'attendance_vs_scheduled' => 'attendance than scheduled',
                'worked_vs_scheduled' => 'worked than scheduled',
                'worked_vs_attendance' => 'worked than attendance',
                default => 'difference',
            };

            $details['tooltip'] = 'No difference'; // Default tooltip
            if ($diffMinutes !== 0) {
                $direction = $diffMinutes > 0 ? 'more' : 'less';
                $details['tooltip'] = sprintf(
                    '%s %s %s',
                    $formattedDiff,
                    $direction,
                    $comparisonText
                );
            }
            // Update the array entry
            $deviationDetails[$deviation] = $details;
        }

        return $deviationDetails;
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
     * Renders the component's Blade view.
     */
    public function render()
    {
        // Prepare data for the view
        $dashboardTotals = $this->getTotals; // Calculate totals via computed property
        $totalDeviationsDetails = $this->showDeviations
          ? $this->totalDeviations
          : null;

        // Pass data explicitly to the view
        return view('livewire.user-dashboard', [
            'dashboardTotals' => $dashboardTotals,
            'totalDeviationsDetails' => $totalDeviationsDetails,
            // Note: $periodData is implicitly available in the view
            // because it's a protected property iterated on in the Blade file
            // If issues persist, we could pass it explicitly too:
            // 'periodDataForView' => $this->periodData
            'isGloballyEnabled' => $this->isGloballyEnabled, // Pass global status
        ]);
    }

    /**
     * Gets the start date of the current period as a Carbon instance (UTC).
     */
    protected function getPeriodStart(): Carbon
    {
        return Carbon::parse($this->currentDate)->startOfDay();
    }

    /**
     * Calculates the end date of the current period based on the view mode.
     *
     * @return Carbon The end date of the period.
     */
    protected function getPeriodEnd(): Carbon
    {
        $startDate = $this->getPeriodStart();

        if ($this->viewMode === 'weekly') {
            return $startDate->clone()->endOfWeek();
        } else {
            return $startDate->clone()->endOfMonth();
        }
    }

    /**
     * Fetches user data for the current period and triggers processing.
     */
    protected function loadPeriodDataAndTotals(): void
    {
        $startDate = $this->getPeriodStart();
        $endDate = $this->getPeriodEnd();

        // Get raw data using the service
        $rawData = $this->userDataService->getDataForUserAndDateRange($this->user, $startDate, $endDate);

        // Process the raw data for the view
        $this->periodData = $this->processPeriodData(
            $rawData,
            $startDate,
            $endDate
        );
    }

    /**
     * Iterates through the date range, processing raw schedule, leave, attendance,
     * and worked data for each day into a structured format for the view.
     *
     * @param  array  $data  Raw data collections ('schedules', 'leaves', 'attendances', 'time_entries').
     * @param  Carbon  $start  Start date of the period (UTC).
     * @param  Carbon  $end  End date of the period (UTC).
     * @return array Processed daily data keyed by date string.
     */
    protected function processPeriodData(
        array $data,
        Carbon $start,
        Carbon $end
    ): array {
        // Work only in UTC
        $dates = collect();
        $cursor = $start->copy();

        // Iterate through each day in the date range.
        while ($cursor->lte($end)) {
            $dateString = $cursor->toDateString();

            // Extract and process data subsets for the current day.
            $scheduleData = $this->processScheduleData($data['schedules'], $cursor);
            $leaveData = $this->processLeaveData(
                $data['leaves'],
                $dateString,
                $data['schedules']
            );
            $attendanceData = $this->processAttendanceData(
                $data['attendances'],
                $dateString
            );
            $workedData = $this->processWorkedData(
                $data['time_entries'],
                $dateString
            );

            // Calculate daily deviations
            $deviationDetails = $this->calculateDailyDeviations([
                'scheduled' => $scheduleData,
                'attendance' => $attendanceData,
                'worked' => $workedData,
                'leave' => $leaveData, // Pass leave data in case it affects calculations later
            ]);

            // Structure the data for the current day.
            $dates->put($dateString, [
                'date' => $dateString,
                'scheduled' => $scheduleData,
                'leave' => $leaveData,
                'attendance' => $attendanceData,
                'worked' => $workedData,
                'deviation_details' => $deviationDetails,
            ]);

            // Move to the next day.
            $cursor->addDay();
        }

        return $dates->all();
    }

    /**
     * Processes schedule data for a specific day, considering the active schedule
     * and Odoo's weekday numbering. Handles potential duplicate schedule details.
     *
     * @param  Collection  $schedules  All user schedule records.
     * @param  Carbon  $localDate  The date to process (UTC).
     * @return array Processed schedule data ('duration', 'slots', 'schedule_name').
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

        // Adjust Carbon's dayOfWeek to align with Odoo's approach (0=Mon, ... 6=Sun).
        // By default, Carbon(0=Sun, 1=Mon, ..., 6=Sat).
        $weekday = ($localDate->dayOfWeek + 6) % 7; // 0=Monday, ..., 6=Sunday

        // Retrieve schedule details matching the current weekday
        $details = $activeSchedule->schedule->scheduleDetails->where(
            'weekday',
            $weekday
        );

        // Get the target hours from the schedule's average_hours_day
        $targetHours = $activeSchedule->schedule->average_hours_day ?? 8.0; // Default to 8 hours if not specified
        $targetMinutes = $targetHours * 60;

        // If we have duplicates, we need to find the best combination
        if ($details->count() > 0) {
            // Group details by day_period (morning/afternoon)
            $periodGroups = $details->groupBy('day_period');

            // For each period, select the best entry if there are duplicates
            $selectedDetails = collect();

            foreach ($periodGroups as $period => $periodDetails) {
                if ($periodDetails->count() == 1) {
                    // If only one entry for this period, use it
                    $selectedDetails->push($periodDetails->first());
                } else {
                    // For duplicate entries in the same period, select the one closest to standard hours
                    // For morning, typically 4 hours (240 mins); for afternoon, typically 4 hours (240 mins)
                    $standardPeriodMins = 240; // 4 hours is standard half-day

                    // Find the entry closest to the standard duration
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

            // Sort the selected details by start time
            $selectedDetails = $selectedDetails->sortBy('start');

            // Check if the total is close enough to the target hours
            $totalSelectedMinutes = $selectedDetails->sum(function ($detail) {
                $start = Carbon::parse($detail->start);
                $end = Carbon::parse($detail->end);

                return $start->diffInMinutes($end);
            });
        } else {
            // No duplicates, just use all details
            $selectedDetails = $details->sortBy('start');
        }

        $totalMinutes = 0;
        $slots = [];

        foreach ($selectedDetails as $detail) {
            // Parse times in UTC
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
     * Processes leave data for a specific day, calculating duration based on type
     * (full/half day) and the user's schedule for that day.
     *
     * @param  Collection  $leaves  All user leave records for the period.
     * @param  string  $dateString  The date to process (YYYY-MM-DD string, UTC).
     * @param  Collection|null  $schedules  User schedule records (optional, used for duration calc).
     * @return array|null Processed leave data or null if no leave on this day.
     */
    protected function processLeaveData(
        Collection $leaves,
        string $dateString,
        ?Collection $schedules = null
    ): ?array {
        $leave = $this->findActiveLeave($leaves, $dateString);
        if (! $leave) {
            return null;
        }

        // Determine contextual info based on leave type.
        $contextInfo = match ($leave->type) {
            'department' => $leave->department->name ?? '',
            'category' => $leave->category->name ?? '',
            default => '',
        };

        // Format the time information using start_date and end_date
        $startTime = $leave->start_date->format('H:i');
        $endTime = $leave->end_date->format('H:i');
        $timeRange = "{$startTime} - {$endTime}";

        // Get formatted half-day time using the helper method
        $halfDayTime = $leave->getFormattedHalfDayHours();

        // Calculate duration in minutes for display based on user's scheduled hours
        $durationMinutes = 0;
        $dateCarbon = Carbon::parse($dateString);
        $isMultiDayLeave = $leave->duration_days > 1;
        $isWeekend = $dateCarbon->isWeekend();

        // For full-day leaves, we need to look at the employee's schedule for that day
        if ($leave->duration_days > 0) {
            if ($leave->isHalfDay()) {
                // For half-day leaves, use the exact hours from request_hour fields if available
                if (
                    $leave->request_hour_from !== null &&
                    $leave->request_hour_to !== null
                ) {
                    // Convert decimal hours to minutes
                    $durationMinutes =
                      ($leave->request_hour_to - $leave->request_hour_from) * 60;
                } else {
                    // Try to get scheduled duration for a proper half-day calculation
                    if ($schedules !== null) {
                        $scheduledMinutes = $this->getScheduledDurationForDate(
                            $schedules,
                            $dateString
                        );
                        $durationMinutes = $scheduledMinutes / 2; // Half of scheduled time
                    } else {
                        // Fallback - half day is typically 4 hours (240 minutes)
                        $durationMinutes = 240;
                    }
                }
            } else {
                // For full-day leaves, determine scheduled hours for this day
                if ($schedules !== null) {
                    $scheduledMinutes = $this->getScheduledDurationForDate(
                        $schedules,
                        $dateString
                    );

                    // If scheduled minutes is 0, use standard workday as fallback (except for weekends)
                    if ($scheduledMinutes == 0) {
                        if (! $isWeekend) {
                            $scheduledMinutes = 8 * 60; // 8 hours = 480 minutes standard workday
                        }
                    }

                    // For this specific day, we only want the hours for THIS day, not multiplied by multi-day count
                    $durationMinutes = $scheduledMinutes;
                } else {
                    // Fallback - standard work day (8 hours = 480 minutes) for weekdays only
                    if (! $isWeekend) {
                        $durationMinutes = 8 * 60; // Standard workday
                    } else {
                        $durationMinutes = 0; // No hours for weekend unless scheduled
                    }
                }
            }
        }

        // Ensure we always have a minimum duration for approved leaves on weekdays
        if ($leave->status === 'validate' && $durationMinutes == 0 && ! $isWeekend) {
            // Use a full standard day as fallback (8 hours) for weekdays
            $durationMinutes = 8 * 60;
        }

        // For weekend validated leaves, ensure we have a standard duration if requested
        if ($leave->status === 'validate' && $isWeekend && $durationMinutes == 0) {
            $durationMinutes = 8 * 60; // Standard 8 hours for weekend leaves
        }

        $durationFormatted = $this->formatMinutesToHoursMinutes($durationMinutes);

        // Format the duration appropriately for full and half days
        $durationText = '';
        if ($leave->duration_days == 0.5) {
            $timeInfo = $leave->isMorningLeave()
              ? 'Morning'
              : ($leave->isAfternoonLeave()
                ? 'Afternoon'
                : '');
            $durationText = 'Half day'.($timeInfo ? " ($timeInfo)" : '');
        } elseif ($leave->duration_days == 1) {
            $durationText = '1 day';
        } else {
            $durationText = CarbonInterval::days($leave->duration_days)
                ->cascade()
                ->forHumans(['parts' => 2]);
        }

        return [
            'type' => $leave->type,
            'context' => $contextInfo,
            'leave_type' => $leave->leaveType->name ?? '[No Type Set]',
            'duration' => $durationText,
            'duration_hours' => $durationFormatted,
            'duration_days' => $leave->duration_days,
            'status' => $leave->status ?? 'validate',
            'is_half_day' => $leave->isHalfDay(),
            'time_period' => $leave->isMorningLeave()
              ? 'morning'
              : ($leave->isAfternoonLeave()
                ? 'afternoon'
                : 'full-day'),
            'time_range' => $timeRange,
            'half_day_time' => $halfDayTime,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'actual_minutes' => $durationMinutes, // Add the actual minutes for accurate totals calculation
            'leave_type_description' => $leave->leaveType?->description, // Add the description
        ];
    }

    /**
     * Processes attendance data for a specific day from DeskTime/SystemPin records.
     *
     * @param  Collection  $attendances  All user attendance records for the period.
     * @param  string  $dateString  The date to process (YYYY-MM-DD string, UTC).
     * @return array Processed attendance data ('duration', 'is_remote', 'times').
     */
    protected function processAttendanceData(
        Collection $attendances,
        string $dateString
    ): array {
        // Parse the target date
        $targetDate = Carbon::parse($dateString)->startOfDay();
        $isMonday = $targetDate->isMonday();

        // Use Collection methods for filtering
        $attendance = $attendances
            ->filter(function ($record) use ($targetDate) {
                // Skip records with no date
                if (! $record->date) {
                    return false;
                }

                // Ensure comparison is done at day precision in UTC
                $recordDate =
                  $record->date instanceof Carbon
                    ? $record->date
                    : Carbon::parse($record->date);

                return $recordDate->startOfDay()->equalTo($targetDate);
            })
            ->first();

        // Default values for empty result
        if (! $attendance) {
            return ['duration' => '0h 0m', 'is_remote' => false, 'times' => []];
        }

        // Extract attendance data
        $isRemote = (bool) $attendance->is_remote;
        $durationMinutes = 0;
        $times = [];

        // Handle different attendance types
        if ($isRemote) {
            // Remote: use presence_seconds
            $durationMinutes = $attendance->presence_seconds / 60;
        } else {
            // In-office: try to use start/end times, fall back to presence_seconds
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
     *
     * @param  Collection  $timeEntries  All user time entries for the period.
     * @param  string  $dateString  The date to process (YYYY-MM-DD string, UTC).
     * @return array Processed worked data ('duration', 'projects', 'detailed_entries').
     */
    protected function processWorkedData(
        Collection $timeEntries,
        string $dateString
    ): array {
        // Default structure - include all fields
        $defaultStructure = [
            'duration' => '0h 0m',
            'projects' => [],
            'detailed_entries' => [],
        ];

        // Filter entries for this specific day
        $targetDate = Carbon::parse($dateString)->startOfDay();
        $filtered = $timeEntries->filter(function ($entry) use ($targetDate) {
            if (! $entry->date) {
                return false;
            }

            $entryDate =
              $entry->date instanceof Carbon
                ? $entry->date
                : Carbon::parse($entry->date);

            return $entryDate->startOfDay()->equalTo($targetDate);
        });

        // Early return with default structure if no entries
        if ($filtered->isEmpty()) {
            return $defaultStructure;
        }

        try {
            // Calculate total minutes worked this day
            $totalMinutes = $filtered->sum(function ($entry) {
                // Use the duration_seconds column, which should now be accurate
                // Fallback gracefully if it happens to be null or not set
                return ($entry->duration_seconds ?? 0) / 60;
            });

            // Group entries by project and extract tasks
            $projects = $filtered
                ->groupBy(function ($entry) {
                    return data_get($entry, 'project.name', 'Unknown Project');
                })
                ->map(function ($group, $projectName) {
                    return [
                        'name' => $projectName,
                        'tasks' => $group
                            ->pluck('task.name')
                            ->filter()
                            ->unique()
                            ->values()
                            ->all(),
                    ];
                })
                ->values()
                ->all();

            // Create detailed entries for tooltip display
            $detailedEntries = $filtered
                ->map(function ($entry) {
                    // Calculate minutes from the reliable duration_seconds field
                    $minutes = ($entry->duration_seconds ?? 0) / 60;

                    return [
                        'project' => data_get($entry, 'project.name', 'Unknown Project'),
                        'task' => data_get($entry, 'task.name'),
                        'description' => $entry->description ?? '',
                        'duration' => $this->formatMinutesToHoursMinutes($minutes),
                        'status' => $entry->status ?? 'none',
                    ];
                })
                ->values()
                ->all();

            return [
                'duration' => $this->formatMinutesToHoursMinutes($totalMinutes),
                'projects' => $projects,
                'detailed_entries' => $detailedEntries,
            ];
        } catch (\Exception $e) {
            // Log error and return default structure on failure
            Log::error('Error processing worked data', [
                'exception' => $e->getMessage(),
                'date' => $dateString,
                'user_id' => $this->user->id,
            ]);

            return $defaultStructure;
        }
    }

    /**
     * Finds the user's active schedule record for a specific date.
     *
     * @param  Collection  $schedules  User's schedule history.
     * @param  string  $dateString  Date to check (YYYY-MM-DD string, UTC).
     * @return object|null The active UserSchedule model instance or null.
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
            $until = $schedule->effective_until
              ? Carbon::parse($schedule->effective_until)
              : null;

            return $from->lte($dateCarbon) && (! $until || $until->gte($dateCarbon));
        });
    }

    /**
     * Finds the user's active leave record for a specific date.
     *
     * @param  Collection  $leaves  User's leave history for the period.
     * @param  string  $dateString  Date to check (YYYY-MM-DD string, UTC).
     * @return object|null The active UserLeave model instance or null.
     */
    protected function findActiveLeave(
        Collection $leaves,
        string $dateString
    ): ?object {
        $dayCarbon = Carbon::parse($dateString);

        return $leaves->first(function ($leave) use ($dayCarbon) {
            return $leave->start_date->lte($dayCarbon) &&
              $leave->end_date->gte($dayCarbon);
        });
    }

    /**
     * Calculates aggregate totals (in minutes) for scheduled, attendance, worked,
     * and actual leave time across the entire period (excluding future dates).
     *
     * This is a computed property, cached by Livewire per request.
     *
     * @return array Aggregated totals keyed by type.
     */
    #[Computed(persist: false)]
    public function getTotals(): array
    {
        // Ensure periodData is loaded if not already
        if (empty($this->periodData)) {
            $this->loadPeriodDataAndTotals();
        }

        return collect($this->periodData)->reduce(
            function ($totals, $day) {
                // Skip future dates (only include past days and today)
                $dayDate = Carbon::parse($day['date']);
                if ($dayDate->isFuture()) {
                    return $totals; // Skip this day's data
                }

                $totals['scheduled'] += $this->durationToMinutes(
                    $day['scheduled']['duration']
                );
                $totals['attendance'] += $this->durationToMinutes(
                    $day['attendance']['duration']
                );
                $totals['worked'] += $this->durationToMinutes(
                    $day['worked']['duration']
                );

                // Add leave minutes when a leave exists AND it's validated
                if (
                    isset($day['leave']) &&
                    isset($day['leave']['status']) &&
                    $day['leave']['status'] === 'validate'
                ) {
                    // Use actual_minutes when available, which accounts for schedule
                    if (array_key_exists('actual_minutes', $day['leave'])) {
                        // Only add to total leave if it's NOT the remote work type
                        if (isset($day['leave']['leave_type']) && ! Str::contains($day['leave']['leave_type'], 'Horas Teletrabajo')) {
                            $totals['leave'] += $day['leave']['actual_minutes'];
                        }
                    }
                    // Fallback to duration_hours
                    elseif (array_key_exists('duration_hours', $day['leave'])) {
                        // Only add to total leave if it's NOT the remote work type
                        if (isset($day['leave']['leave_type']) && ! Str::contains($day['leave']['leave_type'], 'Horas Teletrabajo')) {
                            $totals['leave'] += $this->durationToMinutes(
                                $day['leave']['duration_hours']
                            );
                        }
                    }
                }

                return $totals; // Return the accumulator
            },
            ['scheduled' => 0, 'attendance' => 0, 'worked' => 0, 'leave' => 0] // Initial value for accumulator
        );
    }

    /**
     * Utility function to convert a duration string ("Xh Ym") into total minutes.
     *
     * @param  string  $duration  The duration string (e.g., "8h 15m", "30m", "2h").
     * @return int Total minutes.
     */
    protected function durationToMinutes(string $duration): int
    {
        // Default values
        $hours = 0;
        $minutes = 0;

        // Try to parse using sscanf
        sscanf($duration, '%dh %dm', $hours, $minutes);

        // Ensure values are integers, default to 0 if parsing failed or yielded null
        $hours = (int) $hours;
        $minutes = (int) $minutes;

        return $hours * 60 + $minutes;
    }

    /**
     * Utility function to format total minutes into a human-readable "Xh Ym" string.
     *
     * @param  float  $minutes  Total minutes.
     * @return string Formatted duration string.
     */
    public function formatMinutesToHoursMinutes(float $minutes): string
    {
        if ($minutes < 0) {
            // Handle negative minutes if necessary
            $minutes = 0;
        }

        // Calculate total hours and remaining minutes manually
        $totalHours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        // Format the string manually
        return sprintf('%dh %dm', $totalHours, $remainingMinutes);
    }

    /**
     * Alias for formatMinutesToHoursMinutes, potentially used internally.
     */
    protected function formatDuration(float $minutes): string
    {
        return $this->formatMinutesToHoursMinutes($minutes);
    }

    /**
     * Updates the component's current date property.
     *
     * @param  Carbon  $date  The new start date.
     */
    protected function setPeriodStart(Carbon $date): void
    {
        $this->currentDate = $date->toDateString();
    }

    /**
     * Helper to retrieve the total scheduled duration (in minutes) for a specific date.
     *
     * @param  Collection  $schedules  User's schedule history.
     * @param  string  $dateString  Date to check (YYYY-MM-DD string, UTC).
     * @return int Total scheduled minutes for the day.
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
     * Calculates deviation details (percentage, difference, tooltip, display flag)
     * for a single day based on its processed schedule, attendance, worked, and leave data.
     *
     * @param  array  $dayData  Processed data for a single day.
     * @return array Deviation details for the day.
     */
    protected function calculateDailyDeviations(array $dayData): array
    {
        $deviationDetails = [
            'attendance_vs_scheduled' => [
                'percentage' => 0,
                'difference_minutes' => 0,
                'tooltip' => '',
                'class' => '', // Class is handled in the Blade view
                'should_display' => false, // Flag to control rendering
            ],
            'worked_vs_scheduled' => [
                'percentage' => 0,
                'difference_minutes' => 0,
                'tooltip' => '',
                'class' => '', // Class is handled in the Blade view
                'should_display' => false, // Flag to control rendering
            ],
            'worked_vs_attendance' => [
                'percentage' => 0,
                'difference_minutes' => 0,
                'tooltip' => '',
                'class' => '', // Class is handled in the Blade view
                'should_display' => false, // Flag to control rendering
            ],
        ];

        // Convert durations to minutes for calculation
        $scheduledMinutes = $this->durationToMinutes(
            $dayData['scheduled']['duration']
        );
        $attendanceMinutes = $this->durationToMinutes(
            $dayData['attendance']['duration']
        );
        $workedMinutes = $this->durationToMinutes($dayData['worked']['duration']);
        $leaveMinutes =
          isset($dayData['leave']['actual_minutes']) &&
          $dayData['leave']['status'] === 'validate'
            ? $dayData['leave']['actual_minutes']
            : 0;

        // Determine leave minutes to subtract, ignoring the remote work leave type
        $isRemoteWorkLeave = Str::contains(
            $dayData['leave']['leave_type'] ?? '',
            'Horas Teletrabajo'
        );
        $leaveMinutesToSubtract =
          $leaveMinutes > 0 && ! $isRemoteWorkLeave ? $leaveMinutes : 0;

        // We consider the 'effective scheduled time' as scheduled minus leave
        $effectiveScheduledMinutes = max(
            0,
            $scheduledMinutes - $leaveMinutesToSubtract
        );

        // Calculate actual differences in minutes
        $diffAttVsSch = $attendanceMinutes - $effectiveScheduledMinutes;
        $diffWorkVsSch = $workedMinutes - $effectiveScheduledMinutes;
        $diffWorkVsAtt = $workedMinutes - $attendanceMinutes;

        // Store differences
        $deviationDetails['attendance_vs_scheduled'][
          'difference_minutes'
        ] = $diffAttVsSch;
        $deviationDetails['worked_vs_scheduled'][
          'difference_minutes'
        ] = $diffWorkVsSch;
        $deviationDetails['worked_vs_attendance'][
          'difference_minutes'
        ] = $diffWorkVsAtt;

        // --- Calculate Percentages ---

        // Attendance vs Scheduled (considering leave)
        if ($effectiveScheduledMinutes > 0) {
            $deviationDetails['attendance_vs_scheduled']['percentage'] = round(
                ($diffAttVsSch / $effectiveScheduledMinutes) * 100
            );
        } elseif ($attendanceMinutes > 0) {
            // Scheduled (after leave) is 0, but attendance exists
            $deviationDetails['attendance_vs_scheduled']['percentage'] = 100;
        } else {
            // Both are 0
            $deviationDetails['attendance_vs_scheduled']['percentage'] = 0;
        }

        // Worked vs Scheduled (considering leave)
        if ($effectiveScheduledMinutes > 0) {
            $deviationDetails['worked_vs_scheduled']['percentage'] = round(
                ($diffWorkVsSch / $effectiveScheduledMinutes) * 100
            );
        } elseif ($workedMinutes > 0) {
            // Scheduled (after leave) is 0, but worked exists
            $deviationDetails['worked_vs_scheduled']['percentage'] = 100;
        } else {
            // Both are 0
            $deviationDetails['worked_vs_scheduled']['percentage'] = 0;
        }

        // Worked vs Attendance
        if ($attendanceMinutes > 0) {
            $deviationDetails['worked_vs_attendance']['percentage'] = round(
                ($diffWorkVsAtt / $attendanceMinutes) * 100
            );
        } elseif ($workedMinutes > 0) {
            // Attendance is 0, but worked exists
            $deviationDetails['worked_vs_attendance']['percentage'] = 100;
        } else {
            // Both are 0
            $deviationDetails['worked_vs_attendance']['percentage'] = 0;
        }

        // Determine if each deviation should be displayed based on data availability
        $deviationDetails['attendance_vs_scheduled']['should_display'] =
          $effectiveScheduledMinutes > 0 || $attendanceMinutes > 0;
        $deviationDetails['worked_vs_scheduled']['should_display'] =
          $effectiveScheduledMinutes > 0 || $workedMinutes > 0;
        $deviationDetails['worked_vs_attendance']['should_display'] =
          $attendanceMinutes > 0 || $workedMinutes > 0;

        // Format tooltip text for each deviation type
        foreach ($deviationDetails as $deviation => $details) {
            $diffMinutes = $details['difference_minutes'];
            $formattedDiff = $this->formatMinutesToHoursMinutes(abs($diffMinutes));

            // Determine comparison text based on deviation key
            $comparisonText = match ($deviation) {
                'attendance_vs_scheduled' => 'attendance than scheduled',
                'worked_vs_scheduled' => 'worked than scheduled',
                'worked_vs_attendance' => 'worked than attendance',
                default => 'difference',
            };

            $details['tooltip'] = 'No difference'; // Default tooltip
            if ($diffMinutes !== 0) {
                $direction = $diffMinutes > 0 ? 'more' : 'less';
                $details['tooltip'] = sprintf(
                    '%s %s %s',
                    $formattedDiff,
                    $direction,
                    $comparisonText
                );
            }
            // Update the array entry
            $deviationDetails[$deviation] = $details;
        }

        return $deviationDetails;
    }

    /**
     * Load global notification setting.
     */
    protected function loadGlobalNotificationSetting(): void
    {
        $this->isGloballyEnabled = (bool) Setting::getValue(
            'notifications.global_enabled',
            true
        );
    }
}
