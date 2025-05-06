<?php

declare(strict_types=1);

namespace App\Services;

use App\Clients\DesktimeApiClient;
use App\Clients\OdooApiClient;
use App\Clients\ProofhubApiClient;
use App\Jobs\Sync\SyncDesktimeAttendances;
use App\Jobs\Sync\SyncDesktimeUsers;
use App\Jobs\Sync\SyncOdooCategories;
use App\Jobs\Sync\SyncOdooDepartments;
use App\Jobs\Sync\SyncOdooLeaves;
use App\Jobs\Sync\SyncOdooLeaveTypes;
use App\Jobs\Sync\SyncOdooSchedules;
use App\Jobs\Sync\SyncOdooUsers;
use App\Jobs\Sync\SyncProofhubProjects;
use App\Jobs\Sync\SyncProofhubTasks;
use App\Jobs\Sync\SyncProofhubTimeEntries;
use App\Jobs\Sync\SyncProofhubUsers;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Throwable;

class SyncService
{
    /**
     * Map of platforms and their data types with corresponding job classes
     */
    public array $platformDataMap = [
        'odoo' => [
            'users' => SyncOdooUsers::class,
            'departments' => SyncOdooDepartments::class,
            'categories' => SyncOdooCategories::class,
            'leave-types' => SyncOdooLeaveTypes::class,
            'schedules' => SyncOdooSchedules::class,
            'leaves' => SyncOdooLeaves::class,
        ],
        'proofhub' => [
            'users' => SyncProofhubUsers::class,
            'projects' => SyncProofhubProjects::class,
            'tasks' => SyncProofhubTasks::class,
            'time-entries' => SyncProofhubTimeEntries::class,
        ],
        'desktime' => [
            'users' => SyncDesktimeUsers::class,
            'attendances' => SyncDesktimeAttendances::class,
        ],
    ];

    /**
     * Data types that accept date parameters
     */
    public array $dateBasedTypes = [
        'odoo' => ['leaves'],
        'proofhub' => ['time-entries'],
        'desktime' => ['attendances'],
    ];

    /**
     * Data types that accept user ID parameter
     */
    public array $userBasedTypes = [
        'desktime' => ['attendances'],
    ];

    public function __construct(
        protected OdooApiClient $odooApi,
        protected ProofhubApiClient $proofhubApi,
        protected DesktimeApiClient $desktimeApi
    ) {}

    /**
     * Get the array of jobs for user synchronization.
     */
    public function getUsersSyncJobs(): array
    {
        return [
            new SyncOdooUsers($this->odooApi),
            new SyncDesktimeUsers($this->desktimeApi),
            new SyncProofhubUsers($this->proofhubApi),
        ];
    }

    /**
     * Get the array of jobs for user data synchronization.
     *
     * @param  string|null  $fromDate  Optional start date (Y-m-d) for time entries. Defaults to 30 days ago.
     * @param  string|null  $toDate  Optional end date (Y-m-d) for time entries. Defaults to today.
     */
    public function getDataSyncJobs(?string $fromDate = null, ?string $toDate = null): array
    {
        $fromDate = $fromDate ?? now()->subDays(30)->format('Y-m-d');
        $toDate = $toDate ?? now()->format('Y-m-d');

        return [
            new SyncOdooSchedules($this->odooApi),
            new SyncDesktimeAttendances($this->desktimeApi),
            new SyncOdooLeaves($this->odooApi),
            new SyncProofhubProjects($this->proofhubApi),
            new SyncProofhubTasks($this->proofhubApi),
            new SyncProofhubTimeEntries($this->proofhubApi, $fromDate, $toDate),
        ];
    }

    /**
     * Dispatch a consolidated batch of all synchronization jobs,
     * ensuring dependencies are respected via chaining.
     */
    public function dispatchFullSyncBatch(): string
    {
        $today = now()->format('Y-m-d');

        $odooChain = [
            new SyncOdooDepartments($this->odooApi),
            new SyncOdooCategories($this->odooApi),
            new SyncOdooLeaveTypes($this->odooApi),
            new SyncOdooSchedules($this->odooApi),
            new SyncOdooUsers($this->odooApi),
            new SyncOdooLeaves($this->odooApi, $today, $today),
        ];

        $proofhubChain = [
            new SyncProofhubUsers($this->proofhubApi),
            new SyncProofhubProjects($this->proofhubApi),
            new SyncProofhubTasks($this->proofhubApi),
            new SyncProofhubTimeEntries($this->proofhubApi, $today, $today),
        ];

        $desktimeChain = [
            new SyncDesktimeUsers($this->desktimeApi),
            new SyncDesktimeAttendances($this->desktimeApi),
        ];

        $batch = Bus::batch([
            $odooChain,
            $proofhubChain,
            $desktimeChain,
        ])->name('Full Sync Batch (Odoo, ProofHub, DeskTime)')->dispatch();

        return $batch->id;
    }

    /**
     * Dispatch a batch of Odoo synchronization jobs,
     * ensuring dependencies are respected via chaining.
     */
    public function dispatchOdooBatch(): string
    {
        $today = now()->format('Y-m-d');

        $odooChain = [
            new SyncOdooDepartments($this->odooApi),
            new SyncOdooCategories($this->odooApi),
            new SyncOdooLeaveTypes($this->odooApi),
            new SyncOdooSchedules($this->odooApi),
            new SyncOdooUsers($this->odooApi),
            new SyncOdooLeaves($this->odooApi, $today, $today),
        ];

        $batch = Bus::batch([
            $odooChain,
        ])->name('Odoo Sync Batch (Users, Leaves, Schedules, etc.)')->dispatch();

        return $batch->id;
    }

    /**
     * Dispatch a batch of ProofHub synchronization jobs,
     * ensuring dependencies are respected via chaining.
     */
    public function dispatchProofhubBatch(): string
    {
        $today = now()->format('Y-m-d');

        $proofhubChain = [
            new SyncProofhubUsers($this->proofhubApi),
            new SyncProofhubProjects($this->proofhubApi),
            new SyncProofhubTasks($this->proofhubApi),
            new SyncProofhubTimeEntries($this->proofhubApi, $today, $today),
        ];

        $batch = Bus::batch([
            $proofhubChain,
        ])->name('ProofHub Sync Batch (Users, Projects, Tasks, Time)')->dispatch();

        return $batch->id;
    }

    /**
     * Dispatch a batch of DeskTime synchronization jobs,
     * ensuring dependencies are respected via chaining.
     */
    public function dispatchDesktimeBatch(): string
    {
        $desktimeChain = [
            new SyncDesktimeUsers($this->desktimeApi),
            new SyncDesktimeAttendances($this->desktimeApi),
        ];

        $batch = Bus::batch([
            $desktimeChain,
        ])->name('DeskTime Sync Batch (Users, Attendances)')->dispatch();

        return $batch->id;
    }

    /**
     * Dispatch a single synchronization job for a specific platform and type.
     *
     * @throws Throwable
     */
    public function dispatchSingleJob(string $platform, string $type, ?string $userId = null, ?string $fromDate = null, ?string $toDate = null): void
    {
        // Validate platform and type
        if (! isset($this->platformDataMap[$platform]) || ! isset($this->platformDataMap[$platform][$type])) {
            throw new \InvalidArgumentException("Invalid platform or type provided: {$platform} / {$type}");
        }

        // Get API client instance based on platform
        $apiClient = match ($platform) {
            'odoo' => $this->odooApi,
            'proofhub' => $this->proofhubApi,
            'desktime' => $this->desktimeApi,
            default => throw new \InvalidArgumentException("Unknown platform: {$platform}"), // Should not happen due to check above
        };

        // Get job class
        $jobClass = $this->platformDataMap[$platform][$type];

        // Create job instance with appropriate parameters
        $job = null;
        if ($platform === 'odoo' && $type === 'leaves') {
            $job = new $jobClass($apiClient, $fromDate, $toDate);
        } elseif ($platform === 'proofhub' && $type === 'time-entries') {
            $job = new $jobClass($apiClient, $fromDate, $toDate);
        } elseif ($platform === 'desktime' && $type === 'attendances') {
            $userIdInt = $userId !== null ? (int) $userId : null;
            $job = new $jobClass($apiClient, $userIdInt, $fromDate, $toDate);
        } else {
            $job = new $jobClass($apiClient);
        }

        // Dispatch job
        dispatch($job)->delay(now());
    }
}
