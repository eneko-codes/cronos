<?php

namespace App\Livewire;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

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
  ];

  /**
   * Listen for timezone-changed events to refresh the displayed data.
   * Utilizes Livewire 3's attribute-based event listeners.
   */
  #[On('timezone-changed')]
  public function refreshPeriodData(): void
  {
    $this->loadPeriodDataFromCacheOrDb();
  }

  /**
   * Mount lifecycle hook.
   */
  public function mount(int $id): void
  {
    $this->user = User::findOrFail($id);
    $this->currentDate = now()->startOfWeek()->toDateString();
    $this->loadPeriodDataFromCacheOrDb();
  }

  /**
   * Navigates to the previous week and reloads data.
   */
  public function previousPeriod(): void
  {
    $date = Carbon::parse($this->currentDate);
    $this->currentDate = $date->subWeek()->toDateString();
    $this->loadPeriodDataFromCacheOrDb();
  }

  /**
   * Navigates to the next week if it does not exceed the current date.
   */
  public function nextPeriod(): void
  {
    $date = Carbon::parse($this->currentDate);
    $candidate = $date->copy()->addWeek();

    if ($candidate->startOf('week')->lte(now())) {
      $this->currentDate = $candidate->toDateString();
      $this->loadPeriodDataFromCacheOrDb();
    }
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
   * Retrieves the Carbon instance for the start of the current week.
   */
  protected function getPeriodStart(): Carbon
  {
    return Carbon::parse($this->currentDate)->startOf('week');
  }

  /**
   * Loads or re-loads the day-by-day data from cache or from the database if not cached.
   */
  protected function loadPeriodDataFromCacheOrDb(): void
  {
    $startDate = $this->getPeriodStart();
    $endDate = $startDate->copy()->endOf('week');

    $localStart = $startDate->copy()->setTime(0, 0, 0);
    $localEnd = $endDate->copy()->setTime(23, 59, 59);

    $utcStart = $localStart->copy()->timezone('UTC');
    $utcEnd = $localEnd->copy()->timezone('UTC');

    // Build cache keys for each day in the period
    $dailyData = [];
    $currentDate = $localStart->copy();
    $missingDates = [];

    while ($currentDate->lte($localEnd)) {
      $dateKey = sprintf(
        'user:%d:date:%s',
        $this->user->id,
        $currentDate->toDateString()
      );

      $cachedDay = Cache::get($dateKey);
      if ($cachedDay) {
        $dailyData[$currentDate->toDateString()] = $cachedDay;
      } else {
        $missingDates[] = $currentDate->toDateString();
      }

      $currentDate->addDay();
    }

    // If we have missing dates, fetch them from DB
    if (!empty($missingDates)) {
      $data = $this->user->getDataForDateRange($utcStart, $utcEnd);
      $processedData = $this->processPeriodData($data, $localStart, $localEnd);

      // Cache each day's data separately and merge with existing cached data
      foreach ($processedData as $date => $dayData) {
        if (in_array($date, $missingDates)) {
          $dateKey = sprintf('user:%d:date:%s', $this->user->id, $date);
          Cache::put($dateKey, $dayData, now()->addMinutes(120));
          $dailyData[$date] = $dayData;
        }
      }
    }

    // Sort by date and update the component's data
    ksort($dailyData);
    $this->periodData = $dailyData;
    $this->totals = $this->calculateTotals($dailyData);
  }

  /**
   * Processes raw data from model queries into structured day-by-day data.
   */
  protected function processPeriodData(
    array $data,
    Carbon $start,
    Carbon $end
  ): array {
    $timezone = session('timezone', 'UTC');
    $dates = [];
    $cursor = $start->copy();

    // Iterate through each day in the date range.
    while ($cursor->lte($end)) {
      $dateString = $cursor->toDateString();

      // Extract and process data subsets for the current day.
      $scheduleData = $this->processScheduleData($data['schedules'], $cursor);
      $leaveData = $this->processLeaveData($data['leaves'], $dateString);
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
        'date' => $cursor->copy()->setTimezone($timezone)->format('Y-m-d'),
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
   * Calculates totals (scheduled, attendance, worked) in minutes.
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
        return $totals;
      },
      ['scheduled' => 0, 'attendance' => 0, 'worked' => 0]
    );
  }

  /**
   * Converts an "Xh Ym" duration string to minutes.
   * Example: "6h 30m" => 390.
   */
  protected function durationToMinutes(string $duration): int
  {
    // We'll parse "Xh" and "Ym".
    // If "Xh" is missing, hours is 0; if "Ym" is missing, minutes is 0.
    $hours = 0;
    $mins = 0;

    if (preg_match('/(\d+)h/', $duration, $h)) {
      $hours = (int) $h[1];
    }
    if (preg_match('/(\d+)m/', $duration, $m)) {
      $mins = (int) $m[1];
    }

    return $hours * 60 + $mins;
  }

  /**
   * Formats a duration in minutes into "Xh Ym".
   */
  protected function formatDuration(int $minutes): string
  {
    $hours = intdiv($minutes, 60);
    $remaining = $minutes % 60;

    return sprintf('%dh %dm', $hours, $remaining);
  }

  /**
   * Processes schedule data for a single day.
   */
  protected function processScheduleData(
    Collection $schedules,
    Carbon $localDate
  ): array {
    $timezone = session('timezone', 'UTC');
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
      $start = Carbon::parse($detail->start)->setTimezone($timezone);
      $end = Carbon::parse($detail->end)->setTimezone($timezone);
      $totalMinutes += $end->diffInMinutes($start);

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
    string $dateString
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

    return [
      'type' => $leave->type,
      'context' => $contextInfo,
      'leave_type' => $leave->leaveType?->name ?? 'Unknown',
      // We keep Odoo's original day-based interval for textual display (e.g. "1 day").
      // This is not used in total hours, so it's fine as a readable string.
      'duration' => CarbonInterval::days($leave->duration_days)
        ->cascade()
        ->forHumans(['parts' => 2]),
    ];
  }

  /**
   * Processes attendance data for a single day.
   */
  protected function processAttendanceData(
    Collection $attendances,
    string $dateString
  ): array {
    $timezone = session('timezone', 'UTC');
    $filtered = $attendances->filter(
      fn($a) => Carbon::parse($a->date)->toDateString() === $dateString
    );

    if ($filtered->isEmpty()) {
      return ['duration' => '0h 0m', 'is_remote' => false, 'times' => []];
    }

    // For simplicity, only use the first attendance record on that day.
    $attendance = $filtered->first();
    $durationMinutes = 0;
    $times = [];

    if ($attendance->is_remote) {
      // Remote attendance: duration is based on presence seconds.
      $durationMinutes = $attendance->presence_seconds / 60;
    } else {
      // In-office attendance: calculate duration from start-end times.
      $start = Carbon::parse($attendance->start)->setTimezone($timezone);
      $end = Carbon::parse($attendance->end)->setTimezone($timezone);
      $durationMinutes = $end->diffInMinutes($start);

      if ($start && $end) {
        $times = [$start->format('H:i'), $end->format('H:i')];
      }
    }

    return [
      'duration' => $this->formatDuration((int) $durationMinutes),
      'is_remote' => $attendance->is_remote,
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
    $filtered = $timeEntries->filter(
      fn($e) => Carbon::parse($e->date)->toDateString() === $dateString
    );
    if ($filtered->isEmpty()) {
      return ['duration' => '0h 0m', 'projects' => []];
    }

    // Calculate total minutes worked for the day.
    $totalMinutes = $filtered->sum(
      fn($entry) => $entry->logged_hours * 60 + ($entry->logged_mins ?? 0)
    );

    // Group time entries by project, list tasks.
    $projects = $filtered
      ->groupBy('project.name')
      ->map(function ($group, $projectName) {
        return [
          'name' => $projectName,
          'tasks' => $group
            ->whereNotNull('task')
            ->pluck('task.name')
            ->unique()
            ->values()
            ->all(),
        ];
      })
      ->values()
      ->all();

    return [
      'duration' => $this->formatDuration($totalMinutes),
      'projects' => $projects,
    ];
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
}
