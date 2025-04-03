<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\UserAttendance;
use App\Services\DesktimeApiCalls;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Class SyncDesktimeAttendances
 *
 * Fetches attendance from DeskTime for each user or an optional subset,
 * updates the local DB, and (via BaseSyncJob) flushes the entire cache when done.
 */
class SyncDesktimeAttendances extends BaseSyncJob
{
  /**
   * The priority of the job in the queue.
   *
   * @var int
   */
  public int $priority = 2;

  /**
   * Removes explicit protected DesktimeApiCalls $desktime,
   * using parent's protected ?DesktimeApiCalls $desktime instead.
   */
  private ?int $userId;
  private ?string $fromDate;
  private ?string $toDate;

  public function __construct(
    DesktimeApiCalls $desktime,
    ?int $userId = null,
    ?string $fromDate = null,
    ?string $toDate = null
  ) {
    // Assign to parent's protected $desktime
    $this->desktime = $desktime;

    $this->userId = $userId;
    $this->fromDate = $fromDate;
    $this->toDate = $toDate;
  }

  /**
   * Main execution logic called by BaseSyncJob::handle().
   *
   * @throws Exception
   */
  protected function execute(): void
  {
    $dates = $this->getDatesRange();

    // Process each date in the range
    foreach ($dates as $carbonDate) {
      $this->processAttendanceForDate($carbonDate->format('Y-m-d'));
    }
  }

  /**
   * Generates an array of dates to process based on the specified range.
   *
   * @return array An array of Carbon instances representing each day.
   */
  private function getDatesRange(): array
  {
    $startDate = $this->fromDate
      ? Carbon::parse($this->fromDate)
      : Carbon::today();
    $endDate = $this->toDate ? Carbon::parse($this->toDate) : $startDate;

    return $startDate->toPeriod($endDate)->toArray();
  }

  /**
   * Processes attendance data for a specific date.
   */
  private function processAttendanceForDate(string $date): void
  {
    // Get users to sync
    $users = $this->userId
      ? User::where('id', $this->userId)
        ->where('desktime_id', '!=', null)
        ->trackable()
        ->get()
      : User::whereNotNull('desktime_id')->trackable()->get();

    if ($this->userId) {
      // Single user mode
      foreach ($users as $user) {
        $attendance = $this->desktime->getSingleEmployee(
          $user->desktime_id,
          $date
        );
        $attendance = $attendance->put('date', $date);
        $this->processAttendanceRecord($user, $date, $attendance);
      }
    } else {
      // Bulk mode
      $employeesData = $this->desktime->getAllEmployees($date, 'day');
      $dateEmployees = collect($employeesData->get($date, []));

      // Process ALL users, not just those in the API response
      foreach ($users as $user) {
        $employeeData = collect(
          $dateEmployees->get((string) $user->desktime_id, [])
        );
        // Always process every user to ensure proper deletion of missing records
        $this->processAttendanceRecord($user, $date, $employeeData);
      }
    }
  }

  /**
   * Processes the attendance for a single user on a single date.
   */
  private function processAttendanceRecord(
    User $user,
    string $date,
    Collection $attendance
  ): void {
    // Case 1: No DeskTime data - Delete any existing remote record for this day
    if ($attendance->isEmpty() || $attendance->get('desktimeTime', 0) === 0) {
      UserAttendance::where('user_id', $user->id)
        ->whereDate('date', $date)
        ->where('is_remote', true)
        ->delete();

      return;
    }

    $newPresenceSeconds = $attendance->get('desktimeTime', 0);

    // Use updateOrCreate to either create a new record or update an existing one
    UserAttendance::updateOrCreate(
      [
        'user_id' => $user->id,
        'date' => $date,
        'is_remote' => true,
      ],
      [
        'presence_seconds' => $newPresenceSeconds,
        'start' => null,
        'end' => null,
      ]
    );
  }
}
