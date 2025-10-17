<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Desktime;

use App\Actions\Desktime\CheckDesktimeHealthAction;
use App\Actions\Desktime\ProcessDesktimeAttendanceAction;
use App\Clients\DesktimeApiClient;
use App\DataTransferObjects\Desktime\DesktimeAttendanceDTO;
use App\Jobs\Sync\BaseSyncJob;
use Carbon\Carbon;

/**
 * Job to synchronize attendance records from the DeskTime API with the local database.
 *
 * Syncs attendance data for all users within the specified date range.
 */
class SyncDesktimeAttendancesJob extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 2;

    protected DesktimeApiClient $desktime;

    private ?string $fromDate;

    private ?string $toDate;

    /**
     * Constructs a new SyncDesktimeAttendancesJob instance.
     *
     * @param  DesktimeApiClient  $desktime  The DeskTime API client.
     * @param  string|null  $fromDate  Optional start date in Y-m-d format.
     * @param  string|null  $toDate  Optional end date in Y-m-d format.
     */
    public function __construct(
        DesktimeApiClient $desktime,
        ?string $fromDate = null,
        ?string $toDate = null
    ) {
        $this->desktime = $desktime;
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
    }

    /**
     * Main entry point for the job.
     * Determines the date range to process and iterates over each day, fetching and processing attendance data.
     */
    public function handle(): void
    {
        $dateRange = $this->getDatesRange();
        
        // Retrieve the account timezone once for all attendances
        $timezone = $this->desktime->getAccountTimezone();

        collect($dateRange)->each(function ($date) use ($timezone): void {
            $attendanceDTOs = $this->desktime->getAllAttendanceForDate($date->format('Y-m-d'));

            $attendanceDTOs->each(function (DesktimeAttendanceDTO $attendanceDto) use ($timezone): void {
                (new ProcessDesktimeAttendanceAction)->execute($attendanceDto, $timezone);
            });
        });
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
