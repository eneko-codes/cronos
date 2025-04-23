<?php

namespace App\Http\Controllers;

use App\Jobs\SyncDesktimeAttendances;
use App\Jobs\SyncDesktimeUsers;
use App\Jobs\SyncOdooCategories;
use App\Jobs\SyncOdooDepartments;
use App\Jobs\SyncOdooLeaves;
use App\Jobs\SyncOdooLeaveTypes;
use App\Jobs\SyncOdooSchedules;
use App\Jobs\SyncOdooUsers;
use App\Jobs\SyncProofhubProjects;
use App\Jobs\SyncProofhubTasks;
use App\Jobs\SyncProofhubTimeEntries;
use App\Jobs\SyncProofhubUsers;
use App\Services\DesktimeApiCalls;
use App\Services\OdooApiCalls;
use App\Services\ProofhubApiCalls;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;

class BatchController extends Controller
{
    /**
     * Dispatch a consolidated batch of all synchronization jobs.
     */
    public function dispatchFullSyncBatch(): JsonResponse
    {
        // We'll pass "today" to jobs that need date ranges
        $today = now()->format('Y-m-d');

        $batch = Bus::batch([
            // User details (metadata)
            new SyncOdooUsers(app(OdooApiCalls::class)),
            new SyncOdooDepartments(app(OdooApiCalls::class)),
            new SyncOdooCategories(app(OdooApiCalls::class)),
            new SyncOdooLeaveTypes(app(OdooApiCalls::class)),
            new SyncDesktimeUsers(app(DesktimeApiCalls::class)),
            new SyncProofhubUsers(app(ProofhubApiCalls::class)),

            // Activity data
            new SyncOdooSchedules(app(OdooApiCalls::class)),
            new SyncOdooLeaves(app(OdooApiCalls::class), $today, $today),
            new SyncProofhubProjects(app(ProofhubApiCalls::class)),
            new SyncProofhubTasks(app(ProofhubApiCalls::class)),
            new SyncProofhubTimeEntries(app(ProofhubApiCalls::class), $today, $today),
            new SyncDesktimeAttendances(app(DesktimeApiCalls::class)),
        ])
            ->name('full-sync')
            ->dispatch();

        return response()->json([
            'message' => 'Full synchronization batch dispatched successfully.',
            'batch_id' => $batch->id,
        ]);
    }

    /**
     * Dispatch a batch of Odoo synchronization jobs.
     */
    public function dispatchOdooBatch(): JsonResponse
    {
        // We'll pass "today" to jobs that need date ranges
        $today = now()->format('Y-m-d');

        $batch = Bus::batch([
            new SyncOdooUsers(app(OdooApiCalls::class)),
            new SyncOdooDepartments(app(OdooApiCalls::class)),
            new SyncOdooCategories(app(OdooApiCalls::class)),
            new SyncOdooLeaveTypes(app(OdooApiCalls::class)),
            new SyncOdooSchedules(app(OdooApiCalls::class)),
            new SyncOdooLeaves(app(OdooApiCalls::class), $today, $today),
        ])
            ->name('odoo-sync')
            ->dispatch();

        return response()->json([
            'message' => 'Odoo synchronization batch dispatched successfully.',
            'batch_id' => $batch->id,
        ]);
    }

    /**
     * Dispatch a batch of ProofHub synchronization jobs.
     */
    public function dispatchProofhubBatch(): JsonResponse
    {
        // We'll pass "today" to jobs that need date ranges
        $today = now()->format('Y-m-d');

        $batch = Bus::batch([
            new SyncProofhubUsers(app(ProofhubApiCalls::class)),
            new SyncProofhubProjects(app(ProofhubApiCalls::class)),
            new SyncProofhubTasks(app(ProofhubApiCalls::class)),
            new SyncProofhubTimeEntries(app(ProofhubApiCalls::class), $today, $today),
        ])
            ->name('proofhub-sync')
            ->dispatch();

        return response()->json([
            'message' => 'ProofHub synchronization batch dispatched successfully.',
            'batch_id' => $batch->id,
        ]);
    }

    /**
     * Dispatch a batch of DeskTime synchronization jobs.
     */
    public function dispatchDesktimeBatch(): JsonResponse
    {
        $batch = Bus::batch([
            new SyncDesktimeUsers(app(DesktimeApiCalls::class)),
            new SyncDesktimeAttendances(app(DesktimeApiCalls::class)),
        ])
            ->name('desktime-sync')
            ->dispatch();

        return response()->json([
            'message' => 'DeskTime synchronization batch dispatched successfully.',
            'batch_id' => $batch->id,
        ]);
    }
}
