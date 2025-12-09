<?php

declare(strict_types=1);

namespace App\Jobs\Sync\SystemPin;

use App\Actions\SystemPin\CheckSystemPinHealthAction;
use App\Actions\SystemPin\ProcessSystemPinAttendanceAction;
use App\Clients\SystemPinApiClient;
use App\DataTransferObjects\SystemPin\SystemPinAttendanceDTO;
use App\Jobs\Sync\BaseSyncJob;
use Carbon\Carbon;

/**
 * Job to synchronize SystemPin attendance records with the local database.
 *
 * Synchronizes SystemPin attendances into the local database.
 */
class SyncSystemPinAttendancesJob extends BaseSyncJob
{
    /**
     * The priority of the job in the queue.
     */
    public int $priority = 2;

    protected SystemPinApiClient $systempin;

    private ?string $fromDate;

    private ?string $toDate;

    /**
     * Constructs a new SyncSystemPinAttendancesJob instance.
     *
     * @param  SystemPinApiClient  $systempin  The SystemPin API client.
     * @param  string|null  $fromDate  Optional start date in Y-m-d format.
     * @param  string|null  $toDate  Optional end date in Y-m-d format.
     */
    public function __construct(
        SystemPinApiClient $systempin,
        ?string $fromDate = null,
        ?string $toDate = null
    ) {
        $this->systempin = $systempin;
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
    }

    /**
     * Main entry point for the job.
     * Determines the date range to process and fetches attendance data from SystemPin.
     */
    public function handle(): void
    {
        $fromDate = $this->fromDate ?? Carbon::today()->format('Y-m-d');
        $toDate = $this->toDate ?? $fromDate;

        $attendanceDTOs = $this->systempin->getAttendanceData($fromDate, $toDate);

        $attendanceDTOs->each(function (SystemPinAttendanceDTO $attendanceDto): void {
            (new ProcessSystemPinAttendanceAction)->execute($attendanceDto);
        });
    }

    /**
     * Handle a job failure.
     *
     * This method is called automatically by Laravel if the job fails after all retry attempts.
     * It triggers a health check for the SystemPin API and notifies admins if the API is down.
     */
    public function failed(): void
    {
        (new CheckSystemPinHealthAction)($this->systempin);
    }
}
