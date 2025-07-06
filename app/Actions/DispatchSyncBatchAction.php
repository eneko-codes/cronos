<?php

declare(strict_types=1);

namespace App\Actions;

use App\Clients\DesktimeApiClient;
use App\Clients\OdooApiClient;
use App\Clients\ProofhubApiClient;
use App\Clients\SystemPinApiClient;
use App\Jobs\Sync\Desktime\SyncDesktimeAttendances;
use App\Jobs\Sync\Desktime\SyncDesktimeUsers;
use App\Jobs\Sync\Odoo\SyncOdooCategories;
use App\Jobs\Sync\Odoo\SyncOdooDepartments;
use App\Jobs\Sync\Odoo\SyncOdooLeaves;
use App\Jobs\Sync\Odoo\SyncOdooLeaveTypes;
use App\Jobs\Sync\Odoo\SyncOdooSchedules;
use App\Jobs\Sync\Odoo\SyncOdooUsers;
use App\Jobs\Sync\Proofhub\SyncProofhubProjects;
use App\Jobs\Sync\Proofhub\SyncProofhubTasks;
use App\Jobs\Sync\Proofhub\SyncProofhubTimeEntries;
use App\Jobs\Sync\Proofhub\SyncProofhubUsers;
use App\Models\Setting;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class DispatchSyncBatchAction
{
    public function __invoke(): void
    {
        $windowDays = (int) Setting::getValue('sync_window_days', 1);
        $now = now();
        $toDate = $now->format('Y-m-d');
        $fromDate = $now->copy()->subDays($windowDays - 1)->format('Y-m-d');

        // Resolve API clients from the container
        $desktimeApi = app(DesktimeApiClient::class);
        $odooApi = app(OdooApiClient::class);
        $proofhubApi = app(ProofhubApiClient::class);
        $systempinApi = app(SystemPinApiClient::class);

        // Prepare job chains for each platform (order and chaining as in SyncService)
        $odooChain = [
            new SyncOdooDepartments($odooApi),
            new SyncOdooCategories($odooApi),
            new SyncOdooLeaveTypes($odooApi),
            new SyncOdooSchedules($odooApi),
            new SyncOdooUsers($odooApi),
            new SyncOdooLeaves($odooApi, $fromDate, $toDate),
        ];

        $proofhubChain = [
            new SyncProofhubUsers($proofhubApi),
            new SyncProofhubProjects($proofhubApi),
            new SyncProofhubTasks($proofhubApi),
            new SyncProofhubTimeEntries($proofhubApi, $fromDate, $toDate),
        ];

        $desktimeChain = [
            new SyncDesktimeUsers($desktimeApi),
            new SyncDesktimeAttendances($desktimeApi, null, $fromDate, $toDate),
        ];

        $batchName = "Data Sync Batch ({$windowDays} days: {$fromDate} to {$toDate})";

        // Log the batch dispatch
        Log::info(
            "Dispatching sync batch for {$windowDays} day window: {$fromDate} to {$toDate}"
        );

        // Dispatch as a batch with chains for each platform
        Bus::batch([
            $odooChain,
            $proofhubChain,
            $desktimeChain,
        ])
            ->name($batchName)
            ->dispatch();

    }
}
