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
     * Dispatch a consolidated batch of all synchronization jobs,
     * ensuring dependencies are respected via chaining.
     */
    public function dispatchFullSyncBatch(): JsonResponse
    {
        $today = now()->format('Y-m-d');

        // Define chains for jobs with dependencies
        $odooChain = [
            // Phase 1: Sync Odoo metadata
            new SyncOdooDepartments(app(OdooApiCalls::class)),
            new SyncOdooCategories(app(OdooApiCalls::class)),
            new SyncOdooLeaveTypes(app(OdooApiCalls::class)),
            new SyncOdooSchedules(app(OdooApiCalls::class)),
            // Phase 2: Sync Odoo Users (depends on metadata)
            new SyncOdooUsers(app(OdooApiCalls::class)),
            // Phase 3: Sync Odoo Leaves (depends on Users and LeaveTypes)
            new SyncOdooLeaves(app(OdooApiCalls::class), $today, $today),
        ];

        $proofhubChain = [
            new SyncProofhubUsers(app(ProofhubApiCalls::class)),
            new SyncProofhubProjects(app(ProofhubApiCalls::class)),
            new SyncProofhubTasks(app(ProofhubApiCalls::class)),
            // TimeEntries depends on Users/Projects/Tasks
            new SyncProofhubTimeEntries(app(ProofhubApiCalls::class), $today, $today),
        ];

        $desktimeChain = [
            new SyncDesktimeUsers(app(DesktimeApiCalls::class)),
            // Attendances depends on Users
            new SyncDesktimeAttendances(app(DesktimeApiCalls::class)),
        ];

        $batch = Bus::batch([
            $odooChain,     // Odoo jobs run sequentially within this chain
            $proofhubChain, // Proofhub jobs run sequentially within this chain
            $desktimeChain, // Desktime jobs run sequentially within this chain
        ])->name('full-sync')->dispatch();

        return response()->json([
            'message' => 'Full synchronization batch dispatched successfully.',
            'batch_id' => $batch->id,
        ]);
    }

    /**
     * Dispatch a batch of Odoo synchronization jobs,
     * ensuring dependencies are respected via chaining.
     */
    public function dispatchOdooBatch(): JsonResponse
    {
        $today = now()->format('Y-m-d');

        $odooChain = [
            // Phase 1: Sync Odoo metadata
            new SyncOdooDepartments(app(OdooApiCalls::class)),
            new SyncOdooCategories(app(OdooApiCalls::class)),
            new SyncOdooLeaveTypes(app(OdooApiCalls::class)),
            new SyncOdooSchedules(app(OdooApiCalls::class)),
            // Phase 2: Sync Odoo Users (depends on metadata)
            new SyncOdooUsers(app(OdooApiCalls::class)),
            // Phase 3: Sync Odoo Leaves (depends on Users and LeaveTypes)
            new SyncOdooLeaves(app(OdooApiCalls::class), $today, $today),
        ];

        $batch = Bus::batch([
            $odooChain, // Ensures sequential execution for Odoo jobs
        ])->name('odoo-sync')->dispatch();

        return response()->json([
            'message' => 'Odoo synchronization batch dispatched successfully.',
            'batch_id' => $batch->id,
        ]);
    }

    /**
     * Dispatch a batch of ProofHub synchronization jobs,
     * ensuring dependencies are respected via chaining.
     */
    public function dispatchProofhubBatch(): JsonResponse
    {
        $today = now()->format('Y-m-d');

        $proofhubChain = [
            new SyncProofhubUsers(app(ProofhubApiCalls::class)),
            new SyncProofhubProjects(app(ProofhubApiCalls::class)),
            new SyncProofhubTasks(app(ProofhubApiCalls::class)),
            // TimeEntries depends on Users/Projects/Tasks
            new SyncProofhubTimeEntries(app(ProofhubApiCalls::class), $today, $today),
        ];

        $batch = Bus::batch([
            $proofhubChain, // Ensures sequential execution for Proofhub jobs
        ])->name('proofhub-sync')->dispatch();

        return response()->json([
            'message' => 'ProofHub synchronization batch dispatched successfully.',
            'batch_id' => $batch->id,
        ]);
    }

    /**
     * Dispatch a batch of DeskTime synchronization jobs,
     * ensuring dependencies are respected via chaining.
     */
    public function dispatchDesktimeBatch(): JsonResponse
    {
        $desktimeChain = [
            new SyncDesktimeUsers(app(DesktimeApiCalls::class)),
            // Attendances depends on Users
            new SyncDesktimeAttendances(app(DesktimeApiCalls::class)),
        ];

        $batch = Bus::batch([
            $desktimeChain, // Ensures sequential execution for Desktime jobs
        ])->name('desktime-sync')->dispatch();

        return response()->json([
            'message' => 'DeskTime synchronization batch dispatched successfully.',
            'batch_id' => $batch->id,
        ]);
    }
}
