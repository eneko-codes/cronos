<?php

namespace App\Livewire;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\Gate;

#[Title('User Dashboard')]
class UserDashboard extends Component
{
  /**
   * The user whose data is displayed.
   */
  public User $user;

  /**
   * String date (YYYY-MM-DD) representing the start of the displayed period.
   */
  #[Url]
  public string $currentDate;

  /**
   * View mode: 'weekly' or 'monthly'
   */
  #[Url]
  public string $viewMode = 'weekly';

  /**
   * Display mode: 'table' or 'graph'
   */
  #[Url]
  public string $displayMode = 'table';

  /**
   * Whether to show deviation percentages
   */
  #[Url]
  public bool $showDeviations = false;

  /**
   * The final array of day-by-day data for the chosen period.
   * This array is used directly by the Blade template.
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
   * Aggregated totals (scheduled, attendance, worked) for the displayed period,
   * stored in minutes so we can convert them into "X hours Y minutes" later.
   *
   * @var array{
   *   scheduled: int,
   *   attendance: int,
   *   worked: int,
   *   leave: int
   * }
   */
  protected array $totals = [
    'scheduled' => 0, // in minutes
    'attendance' => 0, // in minutes
    'worked' => 0, // in minutes
    'leave' => 0, // in minutes
  ];

  /**
   * Whether the current user is an admin
   */
  public bool $isAdmin = false;

  /**
   * Mount lifecycle hook.
   */
  public function mount($id = null): void
  {
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

    // Always start with the current period based on view mode
    $this->setPeriodStart(
      $this->viewMode === 'weekly'
        ? now()->startOfWeek()
        : now()->startOfMonth()
    );

    $this->checkPermissions();

    // Load cache data for the current period
    $this->loadPeriodDataAndTotals();
  }

  /**
   * Toggle showing deviation percentages
   */
  public function toggleDeviations(): void
  {
    $this->showDeviations = !$this->showDeviations;
    // Reload data when the toggle changes to ensure deviation_details are populated/updated
    $this->loadPeriodDataAndTotals();
  }

  /**
   * Set the view mode to weekly or monthly
   *
   * @param 'weekly'|'monthly' $mode
   */
  public function setViewMode(string $mode): void
  {
    if (!in_array($mode, ['weekly', 'monthly'])) {
      throw new \InvalidArgumentException(
        'View mode must be either "weekly" or "monthly"'
      );
    }

    $this->viewMode = $mode;

    // Always reset to the current week or month when switching view modes
    $this->setPeriodStart(
      $this->viewMode === 'weekly'
        ? now()->startOfWeek()
        : now()->startOfMonth()
    );

    // Reload data for the new period
    $this->loadPeriodDataAndTotals(); // Renamed for clarity
  }

  /**
   * Setter for viewMode property
   */
  public function setViewModeAttribute(string $value): void
  {
    $this->setViewMode($value);
  }

  /**
   * Navigate to previous period.
   */
  public function previousPeriod(): void
  {
    $startDate = $this->getPeriodStart();

    if ($this->viewMode === 'weekly') {
      $this->setPeriodStart($startDate->subWeek());
    } else {
      $this->setPeriodStart($startDate->subMonth());
    }

    $this->loadPeriodDataAndTotals(); // Renamed for clarity
  }

  /**
   * Navigate to next period.
   */
  public function nextPeriod(): void
  {
    $startDate = $this->getPeriodStart();

    if ($this->viewMode === 'weekly') {
      $this->setPeriodStart($startDate->addWeek());
    } else {
      $this->setPeriodStart($startDate->addMonth());
    }

    $this->loadPeriodDataAndTotals(); // Renamed for clarity
  }

  /**
   * Retrieves the data for the displayed period (day-by-day).
   */
  #[Computed]
  public function periodData(): array
  {
    // Directly return the protected property, assuming it's loaded by actions.
    return $this->periodData;
  }

  /**
   * Returns aggregated totals (Scheduled, Attendance, Worked) for the displayed period,
   * stored in minutes.
   */
  #[Computed]
  public function totals(): array
  {
    // Directly return the protected property.
    return $this->totals;
  }

  /**
   * Calculate total deviations for the whole period, including formatted text and classes.
   */
  #[Computed(persist: false)]
  public function totalDeviations(): array
  {
    $deviationDetails = [
      'attendance_vs_scheduled' => [
        'percentage' => 0,
        'difference_minutes' => 0,
        'tooltip' => '',
        'class' => '',
      ],
      'worked_vs_scheduled' => [
        'percentage' => 0,
        'difference_minutes' => 0,
        'tooltip' => '',
        'class' => '',
      ],
      'worked_vs_attendance' => [
        'percentage' => 0,
        'difference_minutes' => 0,
        'tooltip' => '',
        'class' => '',
      ],
    ];

    // Access totals via the computed property accessor
    $totals = $this->totals(); // This will now just return the protected property

    // Calculate differences and percentages
    $diffAttVsSch = $totals['attendance'] - $totals['scheduled'];
    $diffWorkVsSch = $totals['worked'] - $totals['scheduled'];
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

    // Calculate percentages safely
    if ($totals['scheduled'] > 0) {
      $deviationDetails['attendance_vs_scheduled']['percentage'] = round(
        ($diffAttVsSch / $totals['scheduled']) * 100
      );
      $deviationDetails['worked_vs_scheduled']['percentage'] = round(
        ($diffWorkVsSch / $totals['scheduled']) * 100
      );
    }
    if ($totals['attendance'] > 0) {
      $deviationDetails['worked_vs_attendance']['percentage'] = round(
        ($diffWorkVsAtt / $totals['attendance']) * 100
      );
    } elseif ($diffWorkVsAtt > 0) {
      // Handle worked > 0 and attendance = 0 case if needed (e.g., 100%)
      $deviationDetails['worked_vs_attendance']['percentage'] = 100;
    }

    // Format tooltip and class
    foreach ($deviationDetails as $deviation => $details) {
      $diffMinutes = $details['difference_minutes'];
      $percentage = $details['percentage'];
      $formattedDiff = $this->formatMinutesToHoursMinutes(abs($diffMinutes));

      // Determine comparison text based on deviation key
      $comparisonText = match ($deviation) {
        'attendance_vs_scheduled' => 'attendance than scheduled',
        'worked_vs_scheduled' => 'worked than scheduled',
        'worked_vs_attendance' => 'worked than attendance',
        default => 'difference', // Fallback
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
   * A computed property to display the label "Week of <date>" or "Month of <date>".
   */
  public function getFormattedPeriodProperty(): string
  {
    $format = 'M d, Y';
    return $this->getPeriodStart()->format($format);
  }

  /**
   * Determines if the "next" button should be disabled in the UI.
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
   * Main render method for this Livewire component.
   */
  public function render()
  {
    return view('livewire.user-dashboard');
  }

  /**
   * Gets the period start date as a Carbon instance.
   */
  protected function getPeriodStart(): Carbon
  {
    $date = Carbon::parse($this->currentDate);
    return $this->viewMode === 'weekly'
      ? $date->startOfWeek()
      : $date->startOfMonth();
  }

  /**
   * Loads period data directly from the database.
   */
  protected function loadPeriodDataAndTotals(): void
  {
    $startDate = $this->getPeriodStart();

    // Set end date based on view mode
    $endDate =
      $this->viewMode === 'weekly'
        ? $startDate->copy()->endOfWeek()
        : $startDate->copy()->endOfMonth();

    // Get all user data for the date range
    $userData = $this->user->getDataForDateRange($startDate, $endDate);

    // Process the data for each day in the period
    $this->periodData = $this->processPeriodData(
      $userData,
      $startDate,
      $endDate
    );

    // Calculate totals
    $this->totals = $this->calculateTotals($this->periodData);
  }

  /**
   * Processes raw data from model queries into structured day-by-day data.
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
        'date' => $dateString, // Use UTC date string
        'scheduled' => $scheduleData,
        'leave' => $leaveData,
        'attendance' => $attendanceData,
        'worked' => $workedData,
        'deviation_details' => $deviationDetails, // Add daily deviations
      ]);

      // Move to the next day.
      $cursor->addDay();
    }

    return $dates->all();
  }

  /**
   * Processes schedule data for a single day.
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

    if (!$activeSchedule || !$activeSchedule->schedule) {
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
        ucfirst($detail->day_period) .
        ": {$start->format('H:i')} - {$end->format('H:i')}";
    }

    return [
      'duration' => $this->formatMinutesToHoursMinutes($totalMinutes),
      'slots' => $slots,
      'schedule_name' => $activeSchedule->schedule->description ?? null,
    ];
  }

  /**
   * Processes leave data for a single day.
   */
  protected function processLeaveData(
    Collection $leaves,
    string $dateString,
    ?Collection $schedules = null
  ): ?array {
    $leave = $this->findActiveLeave($leaves, $dateString);
    if (!$leave) {
      return null;
    }

    // Determine contextual info based on leave type.
    $contextInfo = match ($leave->type) {
      'department' => $leave->department?->name ?? '',
      'category' => $leave->category?->name ?? '',
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
            if (!$isWeekend) {
              $scheduledMinutes = 8 * 60; // 8 hours = 480 minutes standard workday
            }
          }

          // For this specific day, we only want the hours for THIS day, not multiplied by multi-day count
          $durationMinutes = $scheduledMinutes;
        } else {
          // Fallback - standard work day (8 hours = 480 minutes) for weekdays only
          if (!$isWeekend) {
            $durationMinutes = 8 * 60; // Standard workday
          } else {
            $durationMinutes = 0; // No hours for weekend unless scheduled
          }
        }
      }
    }

    // Ensure we always have a minimum duration for approved leaves on weekdays
    if ($leave->status === 'validate' && $durationMinutes == 0 && !$isWeekend) {
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
      $durationText = 'Half day' . ($timeInfo ? " ($timeInfo)" : '');
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
      'leave_type' => $leave->leaveType?->name ?? 'Unknown',
      'duration' => $durationText,
      'duration_hours' => $durationFormatted,
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
    ];
  }

  /**
   * Processes attendance data for a single day.
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
        if (!$record->date) {
          return false;
        }

        // Convert to Carbon if needed and compare at day precision
        $recordDate =
          $record->date instanceof Carbon
            ? $record->date
            : Carbon::parse($record->date);

        return $recordDate->startOfDay()->equalTo($targetDate);
      })
      ->first();

    // Default values for empty result
    if (!$attendance) {
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
   * Processes worked data (time entries) for a single day.
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
      if (!$entry->date) {
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
        return round(($entry->duration_seconds ?? 0) / 60);
        /*
        // Original logic based on API response keys (kept for reference)
        return isset($entry->duration_seconds) && $entry->duration_seconds > 0 // Check if > 0 to prefer this if set correctly
          ? round($entry->duration_seconds / 60)
          : ($entry->logged_hours ?? 0) * 60 + ($entry->logged_mins ?? 0);
        */
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
          $minutes = round(($entry->duration_seconds ?? 0) / 60);
          /*
          // Original logic
          $minutes = isset($entry->duration_seconds)
            ? round($entry->duration_seconds / 60)
            : ($entry->logged_hours ?? 0) * 60 + ($entry->logged_mins ?? 0);
          */

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
   * Finds the active schedule for a given date.
   */
  protected function findActiveSchedule(
    Collection $schedules,
    string $dateString
  ): ?object {
    $dateCarbon = Carbon::parse($dateString);

    return $schedules->first(function ($schedule) use ($dateCarbon) {
      if (!$schedule->effective_from) {
        return false;
      }

      $from = Carbon::parse($schedule->effective_from);
      $until = $schedule->effective_until
        ? Carbon::parse($schedule->effective_until)
        : null;

      return $from->lte($dateCarbon) && (!$until || $until->gte($dateCarbon));
    });
  }

  /**
   * Finds the active leave for a given date.
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
   * Calculates totals (scheduled, attendance, worked, leave) in minutes.
   */
  protected function calculateTotals(array $periodData): array
  {
    // We'll accumulate everything in minutes.
    // We'll parse the "Xh Ym" values back into minutes for summation.
    return collect($periodData)->reduce(
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
          $day['leave'] &&
          isset($day['leave']['status']) &&
          $day['leave']['status'] === 'validate'
        ) {
          // Use actual_minutes when available, which accounts for schedule
          if (isset($day['leave']['actual_minutes'])) {
            $totals['leave'] += $day['leave']['actual_minutes'];
          }
          // Fallback to duration_hours
          elseif (isset($day['leave']['duration_hours'])) {
            $totals['leave'] += $this->durationToMinutes(
              $day['leave']['duration_hours']
            );
          }
        }

        return $totals;
      },
      ['scheduled' => 0, 'attendance' => 0, 'worked' => 0, 'leave' => 0]
    );
  }

  /**
   * Converts an "Xh Ym" duration string to minutes.
   * Example: "6h 30m" => 390.
   */
  protected function durationToMinutes(string $duration): int
  {
    if (!preg_match('/^(\d+)h(?:\s+(\d+)m)?$/', $duration, $matches)) {
      throw new \InvalidArgumentException(
        'Duration must be in format "Xh Ym" or "Xh"'
      );
    }

    $hours = (int) $matches[1];
    $minutes = isset($matches[2]) ? (int) $matches[2] : 0;

    return $hours * 60 + $minutes;
  }

  /**
   * Formats a duration in minutes into "Xh Ym".
   */
  public function formatMinutesToHoursMinutes(int $minutes): string
  {
    if ($minutes < 0) {
      // Handle negative minutes if necessary
      $minutes = 0;
    }
    return CarbonInterval::minutes($minutes)->cascade()->format('%hh %im');
  }

  /**
   * Helper method to format duration, used internally by data processing.
   */
  protected function formatDuration(int $minutes): string
  {
    return $this->formatMinutesToHoursMinutes($minutes);
  }

  /**
   * Sets the period start date.
   */
  protected function setPeriodStart(Carbon $date): void
  {
    $this->currentDate = $date->toDateString();
  }

  /**
   * Checks user permissions.
   */
  protected function checkPermissions(): void
  {
    $currentUser = Auth::user();

    // Check if user can view this user's data
    if (!Gate::allows('view', $this->user)) {
      abort(403, 'You are not authorized to view this user\'s data.');
    }

    // Set admin status
    $this->isAdmin = $currentUser->isAdmin();
  }

  /**
   * Gets the scheduled duration for a specified date in minutes.
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
   * Calculates daily deviations based on processed data for a single day.
   */
  protected function calculateDailyDeviations(array $dayData): array
  {
    $deviationDetails = [
      'attendance_vs_scheduled' => [
        'percentage' => 0,
        'difference_minutes' => 0,
        'tooltip' => '',
        'class' => '',
      ],
      'worked_vs_scheduled' => [
        'percentage' => 0,
        'difference_minutes' => 0,
        'tooltip' => '',
        'class' => '',
      ],
      'worked_vs_attendance' => [
        'percentage' => 0,
        'difference_minutes' => 0,
        'tooltip' => '',
        'class' => '',
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

    // We consider the 'effective scheduled time' as scheduled minus leave
    $effectiveScheduledMinutes = max(0, $scheduledMinutes - $leaveMinutes);

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

    // Calculate attendance vs scheduled (considering leave) - Percentage
    if ($effectiveScheduledMinutes > 0) {
      $deviationDetails['attendance_vs_scheduled']['percentage'] = round(
        ($diffAttVsSch / $effectiveScheduledMinutes) * 100
      );
    } elseif ($scheduledMinutes > 0 && $leaveMinutes >= $scheduledMinutes) {
      $deviationDetails['attendance_vs_scheduled']['percentage'] =
        $attendanceMinutes > 0 ? 100 : 0;
    }

    // Calculate worked vs scheduled (considering leave) - Percentage
    if ($effectiveScheduledMinutes > 0) {
      $deviationDetails['worked_vs_scheduled']['percentage'] = round(
        ($diffWorkVsSch / $effectiveScheduledMinutes) * 100
      );
    } elseif ($scheduledMinutes > 0 && $leaveMinutes >= $scheduledMinutes) {
      $deviationDetails['worked_vs_scheduled']['percentage'] =
        $workedMinutes > 0 ? 100 : 0;
    }

    // Calculate worked vs attendance - Percentage
    if ($attendanceMinutes > 0) {
      $deviationDetails['worked_vs_attendance']['percentage'] = round(
        ($diffWorkVsAtt / $attendanceMinutes) * 100
      );
    } elseif ($workedMinutes > 0) {
      $deviationDetails['worked_vs_attendance']['percentage'] = 100;
    }

    // Format tooltip and class
    foreach ($deviationDetails as $deviation => $details) {
      $diffMinutes = $details['difference_minutes'];
      $percentage = $details['percentage'];
      $formattedDiff = $this->formatMinutesToHoursMinutes(abs($diffMinutes));

      // Determine comparison text based on deviation key
      $comparisonText = match ($deviation) {
        'attendance_vs_scheduled' => 'attendance than scheduled',
        'worked_vs_scheduled' => 'worked than scheduled',
        'worked_vs_attendance' => 'worked than attendance',
        default => 'difference', // Fallback
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
}
