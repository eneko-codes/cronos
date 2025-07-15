<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Desktime;

use App\Actions\Desktime\CheckDesktimeHealthAction;
use App\Clients\DesktimeApiClient;
use App\Jobs\Sync\BaseSyncJob;
use App\Models\User;
use App\Models\UserAttendance;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Job to synchronize attendance records from the DeskTime API with the local database.
 *
 * Supports syncing for all users or a specific user, and can be restricted to a given date range.
 * Ensures local records are up-to-date and removes obsolete entries.
 */
class SyncDesktimeAttendances extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 2;

    /**
     * Optional parameters to filter the data to synchronize.
     *
     * @var int|null If set, only sync attendance for this user.
     * @var string|null Optional start date (Y-m-d).
     * @var string|null Optional end date (Y-m-d).
     */
    protected DesktimeApiClient $desktime;

    private ?int $userId;

    private ?string $fromDate;

    private ?string $toDate;

    /**
     * Constructs a new SyncDesktimeAttendances job instance.
     *
     * @param  DesktimeApiClient  $desktime  The DeskTime API client.
     * @param  int|null  $userId  Optional user ID to sync only one user.
     * @param  string|null  $fromDate  Optional start date in Y-m-d format.
     * @param  string|null  $toDate  Optional end date in Y-m-d format.
     */
    public function __construct(
        DesktimeApiClient $desktime,
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
     * Main entry point for the job.
     * Determines the date range to process and iterates over each day, fetching and updating attendance data for all relevant users.
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    public function handle(): void
    {
        $stats = [
            'received' => 0,
            'skipped' => 0,
            'updated' => 0,
            'created' => 0,
            'deleted' => 0,
        ];
        $dateRange = $this->getDatesRange();
        collect($dateRange)->each(function ($date) use (&$stats): void {
            Log::debug('DeskTime sync: Requesting date', ['date' => $date->format('Y-m-d')]);
            $this->processAttendanceForDate($date->format('Y-m-d'), $stats);
        });
        $dates = collect($dateRange)->map(fn ($date) => $date->format('Y-m-d'))->filter();
        if ($dates->isNotEmpty()) {
            $minDate = $dates->min();
            $maxDate = $dates->max();
            Log::info(class_basename(static::class).' Data range', [
                'min_date' => $minDate,
                'max_date' => $maxDate,
                'attendance_days_count' => $dates->count(),
            ]);
        }
        Log::info(class_basename(static::class).' Sync stats', $stats);
    }

    /**
     * Generates a range of dates to process based on the job parameters.
     *
     * @return array An array of Carbon instances representing each day in the range.
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
     * Processes attendance data for a specific date for all relevant users.
     *
     * @param  string  $date  Date in Y-m-d format.
     */
    private function processAttendanceForDate(string $date, array &$stats): void
    {
        $users = $this->getUsers();
        $stats['received'] += $users->count();
        if ($this->userId) {
            $this->processSingleUserMode($users, $date, $stats);
        } else {
            $this->processBulkMode($users, $date, $stats);
        }
    }

    /**
     * Retrieves the users that should be synchronized (all trackable users with a DeskTime ID, or a specific user).
     *
     * @return Collection Collection of users to sync.
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
     * Processes attendance for a single user on a specific date.
     *
     * @param  Collection  $users  Users to process (should be just one).
     * @param  string  $date  Date in Y-m-d format.
     */
    private function processSingleUserMode(Collection $users, string $date, array &$stats): void
    {
        $users->each(function ($user) use ($date, &$stats): void {
            $attendance = $this->desktime->getSingleEmployee(
                $user->desktime_id,
                $date
            );
            $attendanceData = collect([
                'desktimeTime' => $attendance->desktimeTime,
            ]);
            $this->processAttendanceRecord($user, $date, $attendanceData, $stats);
        });
    }

    /**
     * Processes attendance for all users on a specific date (bulk mode).
     *
     * @param  Collection  $users  Users to process.
     * @param  string  $date  Date in Y-m-d format.
     */
    private function processBulkMode(Collection $users, string $date, array &$stats): void
    {
        $attendanceDTOs = $this->desktime->getAllAttendanceForDate($date);
        \Log::debug('DeskTime API response', ['date' => $date, 'attendanceDTOs' => $attendanceDTOs]);
        $users->each(function ($user) use ($attendanceDTOs, $date, &$stats): void {
            $dto = $attendanceDTOs->get($user->desktime_id);
            if (! $dto) {
                $stats['skipped']++;

                return;
            }
            $attendanceData = collect([
                'desktimeTime' => $dto->desktimeTime,
                'productiveTime' => $dto->productiveTime,
                'arrived' => $dto->arrived,
                'left' => $dto->left,
                'late' => $dto->late,
                'onlineTime' => $dto->onlineTime,
                'offlineTime' => $dto->offlineTime,
                'atWorkTime' => $dto->atWorkTime,
                'afterWorkTime' => $dto->afterWorkTime,
                'beforeWorkTime' => $dto->beforeWorkTime,
                'productivity' => $dto->productivity,
                'efficiency' => $dto->efficiency,
                'work_starts' => $dto->work_starts,
                'work_ends' => $dto->work_ends,
                'notes' => $dto->notes,
                'activeProject' => $dto->activeProject,
            ]);
            $this->processAttendanceRecord($user, $date, $attendanceData, $stats);
        });
    }

    /**
     * Processes the attendance record for a single user on a single date.
     *
     * @param  User  $user  The user to process.
     * @param  string  $date  Date in Y-m-d format.
     * @param  Collection  $attendance  Attendance data from DeskTime.
     */
    private function processAttendanceRecord(
        User $user,
        string $date,
        Collection $attendance,
        array &$stats
    ): void {
        // Case 1: No DeskTime data - Delete any existing remote record for this day
        if ($attendance->isEmpty() || $attendance->get('desktimeTime', 0) === 0) {
            $deleted = $this->deleteRemoteAttendance($user->id, $date);
            if ($deleted) {
                $stats['deleted']++;
            } else {
                $stats['skipped']++;
            }

            return;
        }

        // Case 2: There is DeskTime data - Create or update record
        $presenceSeconds = $attendance->get('desktimeTime', 0);
        $start = is_string($attendance->get('arrived')) ? $attendance->get('arrived') : null;
        $end = is_string($attendance->get('left')) ? $attendance->get('left') : null;
        $result = $this->createOrUpdateAttendance($user->id, $date, $attendance);
        if ($result === 'created') {
            $stats['created']++;
        } elseif ($result === 'updated') {
            $stats['updated']++;
        } else {
            $stats['skipped']++;
        }
    }

    /**
     * Deletes remote attendance records for a user on a specific date.
     *
     * @param  int  $userId  User ID
     * @param  string  $date  Date in Y-m-d format
     */
    private function deleteRemoteAttendance(int $userId, string $date): bool
    {
        $deleted = UserAttendance::where('user_id', $userId)
            ->whereDate('date', $date)
            ->delete();

        return $deleted > 0;
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
    ): string {
        $presenceSeconds = $attendance->get('desktimeTime', 0);
        if (empty($date)) {
            Log::warning(class_basename(static::class).' Skipping: missing required fields', [
                'job' => class_basename(static::class),
                'user_id' => $userId,
                'date' => $date,
                'attendance' => $attendance,
            ]);

            return 'skipped';
        }
        $start = is_string($attendance->get('arrived')) ? $attendance->get('arrived') : null;
        $end = is_string($attendance->get('left')) ? $attendance->get('left') : null;
        $isRemote = empty($start) && empty($end); // true if both are empty/null, false otherwise
        $existing = UserAttendance::where('user_id', $userId)
            ->whereDate('date', $date)
            ->first();
        if ($existing) {
            $existing->update([
                'presence_seconds' => $presenceSeconds,
                'start' => $start,
                'end' => $end,
                'is_remote' => $isRemote,
            ]);

            return 'updated';
        } else {
            UserAttendance::create([
                'user_id' => $userId,
                'date' => $date,
                'is_remote' => $isRemote,
                'presence_seconds' => $presenceSeconds,
                'start' => $start,
                'end' => $end,
            ]);

            return 'created';
        }
    }

    /**
     * Handle a job failure.
     *
     * This method is called automatically by Laravel if the job fails after all retry attempts.
     * It triggers a health check for the DeskTime API and notifies admins if the API is down.
     */
    public function failed(): void
    {
        app(CheckDesktimeHealthAction::class)($this->desktime);
    }
}
