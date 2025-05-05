<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Models\UserAttendance;
use App\Services\DesktimeApiService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;

/**
 * Class SyncDesktimeAttendances
 *
 * Synchronizes attendance data from DeskTime into the local database.
 * Can synchronize all users or just a specific user, and can be limited
 * to a specific date range.
 */
class SyncDesktimeAttendances extends BaseSyncJob
{
    /**
     * The priority of the job in the queue.
     * Lower numbers indicate higher priority.
     */
    public int $priority = 2;

    /**
     * Optional parameters to filter the data to synchronize.
     */
    private ?int $userId;

    private ?string $fromDate;

    private ?string $toDate;

    /**
     * SyncDesktimeAttendances constructor.
     *
     * @param  DesktimeApiService  $desktime  An instance of the DesktimeApiService service
     * @param  int|null  $userId  Optional user ID to sync only one user
     * @param  string|null  $fromDate  Optional start date in Y-m-d format
     * @param  string|null  $toDate  Optional end date in Y-m-d format
     */
    public function __construct(
        DesktimeApiService $desktime,
        ?int $userId = null,
        ?string $fromDate = null,
        ?string $toDate = null
    ) {
        $this->desktime = $desktime;
        $this->userId = $userId;
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
    }

    /**
     * Executes the synchronization process.
     *
     * This method performs the following operations:
     * 1. Determines the date range to process
     * 2. Processes attendance data for each date in the range
     *
     * @throws Exception If any part of the synchronization process fails
     */
    protected function execute(): void
    {
        // Step 1: Determine the date range to process
        $dateRange = $this->getDatesRange();

        // Step 2: Process attendance data for each date
        collect($dateRange)->each(function ($date) {
            $this->processAttendanceForDate($date->format('Y-m-d'));
        });
    }

    /**
     * Generates a range of dates to process based on the parameters.
     *
     * @return array An array of Carbon instances representing each day
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
     *
     * @param  string  $date  Date in Y-m-d format
     */
    private function processAttendanceForDate(string $date): void
    {
        // Get users to sync (either a specific user or all trackable users with desktime_id)
        $users = $this->getUsers();

        if ($this->userId) {
            $this->processSingleUserMode($users, $date);
        } else {
            $this->processBulkMode($users, $date);
        }
    }

    /**
     * Gets the users that should be synchronized.
     *
     * @return Collection Collection of users to sync
     */
    private function getUsers(): Collection
    {
        $query = User::whereNotNull('desktime_id')->trackable();

        if ($this->userId) {
            $query->where('id', $this->userId);
        }

        return $query->get();
    }

    /**
     * Processes attendance in single user mode.
     *
     * @param  Collection  $users  Users to process (should be just one)
     * @param  string  $date  Date in Y-m-d format
     */
    private function processSingleUserMode(Collection $users, string $date): void
    {
        $users->each(function ($user) use ($date) {
            $attendance = $this->desktime->getSingleEmployee(
                $user->desktime_id,
                $date
            );
            $attendance = $attendance->put('date', $date);
            $this->processAttendanceRecord($user, $date, $attendance);
        });
    }

    /**
     * Processes attendance in bulk mode (all users).
     *
     * @param  Collection  $users  Users to process
     * @param  string  $date  Date in Y-m-d format
     */
    private function processBulkMode(Collection $users, string $date): void
    {
        $employeesData = $this->desktime->getAllEmployees($date, 'day');
        $dateEmployees = collect($employeesData->get($date, []));

        // Process ALL users, not just those in the API response
        $users->each(function ($user) use ($dateEmployees, $date) {
            $employeeData = collect(
                $dateEmployees->get((string) $user->desktime_id, [])
            );
            // Always process every user to ensure proper deletion of missing records
            $this->processAttendanceRecord($user, $date, $employeeData);
        });
    }

    /**
     * Processes the attendance for a single user on a single date.
     *
     * @param  User  $user  The user to process
     * @param  string  $date  Date in Y-m-d format
     * @param  Collection  $attendance  Attendance data from DeskTime
     */
    private function processAttendanceRecord(
        User $user,
        string $date,
        Collection $attendance
    ): void {
        // Case 1: No DeskTime data - Delete any existing remote record for this day
        if ($attendance->isEmpty() || $attendance->get('desktimeTime', 0) === 0) {
            $this->deleteRemoteAttendance($user->id, $date);

            return;
        }

        // Case 2: There is DeskTime data - Create or update record
        $this->createOrUpdateAttendance($user->id, $date, $attendance);
    }

    /**
     * Deletes remote attendance records for a user on a specific date.
     *
     * @param  int  $userId  User ID
     * @param  string  $date  Date in Y-m-d format
     */
    private function deleteRemoteAttendance(int $userId, string $date): void
    {
        UserAttendance::where('user_id', $userId)
            ->whereDate('date', $date)
            ->where('is_remote', true)
            ->delete();
    }

    /**
     * Creates or updates an attendance record.
     *
     * @param  int  $userId  User ID
     * @param  string  $date  Date in Y-m-d format
     * @param  Collection  $attendance  Attendance data from DeskTime
     */
    private function createOrUpdateAttendance(
        int $userId,
        string $date,
        Collection $attendance
    ): void {
        $presenceSeconds = $attendance->get('desktimeTime', 0);

        UserAttendance::updateOrCreate(
            [
                'user_id' => $userId,
                'date' => $date,
                'is_remote' => true,
            ],
            [
                'presence_seconds' => $presenceSeconds,
                'start' => null,
                'end' => null,
            ]
        );
    }
}
