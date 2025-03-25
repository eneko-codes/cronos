<?php

namespace App\Livewire;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

#[Title('User Page')]
class UserPage extends Component
{
  /**
   * The user whose data is displayed.
   */
  public User $user;

  /**
   * String date (YYYY-MM-DD) representing the start of the displayed week.
   */
  public string $currentDate;

  /**
   * The final array of day-by-day data for the chosen period.
   * This array is used directly by the Blade template.
   */
  protected array $periodData = [];

  /**
   * Aggregated totals (scheduled, attendance, worked) for the displayed period,
   * stored in minutes so we can convert them into "X hours Y minutes" later.
   */
  protected array $totals = [
    'scheduled' => 0, // in minutes
    'attendance' => 0, // in minutes
    'worked' => 0, // in minutes
    'leave' => 0, // in minutes
  ];

  /**
   * Whether the current user can edit this user's data
   */
  public bool $canEdit = false;

  /**
   * Permissions
   */
  public bool $isAdmin;

  /**
   * Mount lifecycle hook.
   */
  public function mount($id = null): void
  {
    if ($id !== null) {
        $this->user = User::findOrFail($id);
    } else {
        $this->user = Auth::user();
    }

    // Always start with the current week
    $this->setPeriodStart(now()->startOfWeek());
    
    $this->checkPermissions();
    
    // Load cache data for the current week
    $this->loadPeriodDataFromCacheOrDb();
  }

  /**
   * Navigate to previous period.
   */
  public function previousPeriod(): void
  {
    $startDate = $this->getPeriodStart();
    $this->setPeriodStart($startDate->subWeek());
    $this->loadPeriodDataFromCacheOrDb();
  }

  /**
   * Navigate to next period.
   */
  public function nextPeriod(): void
  {
    $startDate = $this->getPeriodStart();
    $this->setPeriodStart($startDate->addWeek());
    $this->loadPeriodDataFromCacheOrDb();
  }

  /**
   * Navigate to current period.
   */
  public function currentPeriod(): void
  {
    $this->setPeriodStart(now()->startOfWeek());
    $this->loadPeriodDataFromCacheOrDb();
  }

  /**
   * Retrieves the data for the displayed period (day-by-day).
   */
  public function getPeriodData(): array
  {
    return $this->periodData;
  }

  /**
   * Returns aggregated totals (Scheduled, Attendance, Worked) for the displayed period,
   * in minutes. The final display format is "X hours Y minutes" in Blade.
   */
  public function getTotals(): array
  {
    return $this->totals;
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
  public function getIsNextPeriodDisabledProperty(): bool
  {
    $current = Carbon::parse($this->currentDate);
    $candidate = $current->copy()->addWeek();
    return $candidate->startOf('week')->gt(now());
  }

  /**
   * Main render method for this Livewire component.
   */
  public function render()
  {
    return view('livewire.user-page');
  }

  /**
   * Gets the period start date as a Carbon instance.
   */
  protected function getPeriodStart(): Carbon
  {
    return Carbon::parse($this->currentDate)->startOfWeek();
  }

  /**
   * Loads period data directly from the database.
   */
  protected function loadPeriodDataFromCacheOrDb(): void
  {
    $startDate = $this->getPeriodStart();
    $endDate = $startDate->copy()->endOfWeek();
    
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
    $dates = [];
    $cursor = $start->copy();

    // Iterate through each day in the date range.
    while ($cursor->lte($end)) {
      $dateString = $cursor->toDateString();

      // Extract and process data subsets for the current day.
      $scheduleData = $this->processScheduleData($data['schedules'], $cursor);
      $leaveData = $this->processLeaveData($data['leaves'], $dateString, $data['schedules']);
      $attendanceData = $this->processAttendanceData(
        $data['attendances'],
        $dateString
      );
      $workedData = $this->processWorkedData(
        $data['time_entries'],
        $dateString
      );

      // Structure the data for the current day.
      $dates[$dateString] = [
        'date' => $dateString, // Use UTC date string
        'scheduled' => $scheduleData,
        'leave' => $leaveData,
        'attendance' => $attendanceData,
        'worked' => $workedData,
      ];

      // Move to the next day.
      $cursor->addDay();
    }

    return $dates;
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

    // Retrieve schedule details matching the current weekday.
    $details = $activeSchedule->schedule->scheduleDetails
      ->where('weekday', $weekday)
      ->sortBy('start');

    $totalMinutes = 0;
    $slots = [];

    foreach ($details as $detail) {
      // Parse times in UTC
      $start = Carbon::parse($detail->start)->setTimezone('UTC');
      $end = Carbon::parse($detail->end)->setTimezone('UTC');
      $totalMinutes += $start->diffInMinutes($end);

      $slots[] =
        ucfirst($detail->day_period) .
        ": {$start->format('H:i')} - {$end->format('H:i')}";
    }

    return [
      'duration' => $this->formatDuration($totalMinutes),
      'slots' => $slots,
    ];
  }

  /**
   * Processes leave data for a single day.
   */
  protected function processLeaveData(
    Collection $leaves,
    string $dateString,
    Collection $schedules = null
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
    
    // Log leave processing information
    Log::debug("Processing leave for date", [
      'date' => $dateString,
      'leave_id' => $leave->id,
      'status' => $leave->status,
      'duration_days' => $leave->duration_days,
      'is_weekend' => $isWeekend,
      'is_multi_day' => $isMultiDayLeave
    ]);
    
    // For full-day leaves, we need to look at the employee's schedule for that day
    if ($leave->duration_days > 0) {
      if ($leave->isHalfDay()) {
        // For half-day leaves, use the exact hours from request_hour fields if available
        if ($leave->request_hour_from !== null && $leave->request_hour_to !== null) {
          // Convert decimal hours to minutes
          $durationMinutes = ($leave->request_hour_to - $leave->request_hour_from) * 60;
        } else {
          // Try to get scheduled duration for a proper half-day calculation
          if ($schedules !== null) {
            $scheduledMinutes = $this->getScheduledDurationForDate($schedules, $dateString);
            $durationMinutes = $scheduledMinutes / 2; // Half of scheduled time
          } else {
            // Fallback - half day is typically 4 hours (240 minutes)
            $durationMinutes = 240;
          }
        }
      } else {
        // For full-day leaves, determine scheduled hours for this day
        if ($schedules !== null) {
          $scheduledMinutes = $this->getScheduledDurationForDate($schedules, $dateString);
          
          // If scheduled minutes is 0, use standard workday as fallback (except for weekends)
          if ($scheduledMinutes == 0) {
            if (!$isWeekend) {
              $scheduledMinutes = 8 * 60; // 8 hours = 480 minutes standard workday
              Log::debug("No scheduled hours found for weekday, using standard workday", [
                'date' => $dateString,
                'standard_minutes' => $scheduledMinutes
              ]);
            } else {
              // For weekends, only count hours if explicitly scheduled
              Log::debug("Weekend day with no scheduled hours, using 0 minutes", [
                'date' => $dateString
              ]);
            }
          }
          
          // For this specific day, we only want the hours for THIS day, not multiplied by multi-day count
          $durationMinutes = $scheduledMinutes;
          
          Log::debug("Using scheduled duration for leave", [
            'date' => $dateString,
            'scheduled_minutes' => $durationMinutes,
          ]);
        } else {
          // Fallback - standard work day (8 hours = 480 minutes) for weekdays only
          if (!$isWeekend) {
            $durationMinutes = 8 * 60; // Standard workday
          } else {
            $durationMinutes = 0; // No hours for weekend unless scheduled
          }
          
          Log::debug("Using standard duration for leave", [
            'date' => $dateString,
            'is_weekend' => $isWeekend,
            'minutes' => $durationMinutes
          ]);
        }
      }
    }
    
    // Ensure we always have a minimum duration for approved leaves on weekdays
    if ($leave->status === 'validate' && $durationMinutes == 0 && !$isWeekend) {
      // Use a full standard day as fallback (8 hours) for weekdays
      $durationMinutes = 8 * 60;
      Log::debug("Setting minimum duration for approved weekday leave with 0 duration", [
        'date' => $dateString,
        'leave_id' => $leave->id,
        'minimum_minutes' => $durationMinutes
      ]);
    }
    
    // For weekend validated leaves, ensure we have a standard duration if requested
    if ($leave->status === 'validate' && $isWeekend && $durationMinutes == 0) {
      $durationMinutes = 8 * 60; // Standard 8 hours for weekend leaves
      Log::debug("Setting standard duration for approved weekend leave", [
        'date' => $dateString,
        'leave_id' => $leave->id,
        'standard_minutes' => $durationMinutes
      ]);
    }
    
    $durationFormatted = $this->formatDuration($durationMinutes);

    // Format the duration appropriately for full and half days
    $durationText = '';
    if ($leave->duration_days == 0.5) {
      $timeInfo = $leave->isMorningLeave() ? 'Morning' : ($leave->isAfternoonLeave() ? 'Afternoon' : '');
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
      'time_period' => $leave->isMorningLeave() ? 'morning' : ($leave->isAfternoonLeave() ? 'afternoon' : 'full-day'),
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
    
    // Optional debug logging
    if ($isMonday) {
      Log::debug("Processing Monday data for {$dateString}", [
        'total_attendances' => $attendances->count(),
      ]);
    }

    // Use Collection methods for filtering
    $attendance = $attendances
      ->filter(function($record) use ($targetDate) {
        // Skip records with no date
        if (!$record->date) return false;
        
        // Convert to Carbon if needed and compare at day precision
        $recordDate = $record->date instanceof Carbon 
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
    $isRemote = (bool)$attendance->is_remote;
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
      'duration' => $this->formatDuration((int) $durationMinutes),
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
      'detailed_entries' => []
    ];
    
    // Filter entries for this specific day
    $targetDate = Carbon::parse($dateString)->startOfDay();
    $filtered = $timeEntries->filter(function($entry) use ($targetDate) {
      if (!$entry->date) return false;
      
      $entryDate = $entry->date instanceof Carbon 
        ? $entry->date 
        : Carbon::parse($entry->date);
        
      return $entryDate->startOfDay()->equalTo($targetDate);
    });
    
    // Log details for debugging if needed
    Log::debug("Time entries for {$dateString}", [
      'user_id' => $this->user->id,
      'filtered_count' => $filtered->count()
    ]);
    
    // Early return with default structure if no entries
    if ($filtered->isEmpty()) {
      return $defaultStructure;
    }

    try {
      // Calculate total minutes worked this day
      $totalMinutes = $filtered->sum(function ($entry) {
        return isset($entry->duration_seconds) 
          ? round($entry->duration_seconds / 60) 
          : (($entry->logged_hours ?? 0) * 60 + ($entry->logged_mins ?? 0));
      });

      // Group entries by project and extract tasks
      $projects = $filtered
        ->groupBy(function($entry) {
          return data_get($entry, 'project.name', 'Unknown Project');
        })
        ->map(function ($group, $projectName) {
          return [
            'name' => $projectName,
            'tasks' => $group->pluck('task.name')
              ->filter()
              ->unique()
              ->values()
              ->all(),
          ];
        })
        ->values()
        ->all();
        
      // Create detailed entries for tooltip display
      $detailedEntries = $filtered->map(function ($entry) {
        $minutes = isset($entry->duration_seconds) 
          ? round($entry->duration_seconds / 60) 
          : (($entry->logged_hours ?? 0) * 60 + ($entry->logged_mins ?? 0));
          
        return [
          'project' => data_get($entry, 'project.name', 'Unknown Project'),
          'task' => data_get($entry, 'task.name'),
          'description' => $entry->description ?? '',
          'duration' => $this->formatDuration($minutes),
          'status' => $entry->status ?? 'none',
        ];
      })
      ->values()
      ->all();

      return [
        'duration' => $this->formatDuration($totalMinutes),
        'projects' => $projects,
        'detailed_entries' => $detailedEntries,
      ];
    } catch (\Exception $e) {
      // Log error and return default structure on failure
      Log::error('Error processing worked data', [
        'exception' => $e->getMessage(),
        'date' => $dateString,
        'user_id' => $this->user->id
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
        if (isset($day['leave']) && $day['leave'] && isset($day['leave']['status']) && $day['leave']['status'] === 'validate') {
          // Use actual_minutes when available, which accounts for schedule
          if (isset($day['leave']['actual_minutes'])) {
            $totals['leave'] += $day['leave']['actual_minutes'];
          } 
          // Fallback to duration_hours
          else if (isset($day['leave']['duration_hours'])) {
            $totals['leave'] += $this->durationToMinutes($day['leave']['duration_hours']);
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
    // Parse a string in the format "Xh Ym" using Str helper
    $hoursMatch = Str::of($duration)->match('/(\d+)h/');
    $hours = $hoursMatch->isNotEmpty() ? (int) $hoursMatch->toString() : 0;
    
    $minsMatch = Str::of($duration)->match('/(\d+)m/');
    $mins = $minsMatch->isNotEmpty() ? (int) $minsMatch->toString() : 0;
    
    return $hours * 60 + $mins;
  }

  /**
   * Formats a duration in minutes into "Xh Ym".
   */
  protected function formatDuration(int $minutes): string
  {
    return CarbonInterval::minutes($minutes)
      ->cascade()
      ->format('%hh %im');
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
    $authUser = Auth::user();
    $this->isAdmin = $authUser && $authUser->is_admin;
    $this->canEdit = ($this->user->id === $authUser?->id || $this->isAdmin);
    
    if ($this->user->doNotTrack) {
      $this->canEdit = false;
    }
  }

  /**
   * Gets the scheduled duration for a specified date in minutes.
   */
  protected function getScheduledDurationForDate(Collection $schedules, string $dateString): int
  {
    $localDate = Carbon::parse($dateString);
    $scheduleData = $this->processScheduleData($schedules, $localDate);
    return $this->durationToMinutes($scheduleData['duration']);
  }
}
