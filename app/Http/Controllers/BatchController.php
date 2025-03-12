<?php

namespace App\Http\Controllers;

use App\Jobs\SyncOdooUsers;
use App\Jobs\SyncOdooDepartments;
use App\Jobs\SyncOdooCategories;
use App\Jobs\SyncDesktimeUsers;
use App\Jobs\SyncProofhubUsers;
use App\Jobs\SyncOdooSchedules;
use App\Jobs\SyncOdooLeaves;
use App\Jobs\SyncProofhubProjects;
use App\Jobs\SyncProofhubTasks;
use App\Jobs\SyncProofhubTimeEntries;
use App\Services\OdooApiCalls;
use App\Services\DesktimeApiCalls;
use App\Services\ProofhubApiCalls;
use Illuminate\Bus\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

class BatchController extends Controller
{
  public function dispatchUserDetailsBatch(): JsonResponse
  {
    // This batch has no jobs that use date ranges
    $batch = Bus::batch([
      new SyncOdooUsers(app(OdooApiCalls::class)),
      new SyncOdooDepartments(app(OdooApiCalls::class)),
      new SyncOdooCategories(app(OdooApiCalls::class)),
      new SyncDesktimeUsers(app(DesktimeApiCalls::class)),
      new SyncProofhubUsers(app(ProofhubApiCalls::class)),
    ])
      ->name('sync-user-details')
      ->then(function (Batch $batch) {
        Log::info(
          'User details synchronization batch completed successfully.',
          ['batch_id' => $batch->id]
        );
      })
      ->catch(function (Batch $batch, Throwable $e) {
        Log::error('User details synchronization batch failed.', [
          'batch_id' => $batch->id,
          'error' => $e->getMessage(),
        ]);
      })
      ->finally(function (Batch $batch) {
        Log::info('User details synchronization batch has finished.', [
          'batch_id' => $batch->id,
        ]);
      })
      ->dispatch();

    return response()->json([
      'message' =>
        'User details synchronization batch dispatched successfully.',
      'batch_id' => $batch->id,
    ]);
  }

  public function dispatchUserDataBatch(): JsonResponse
  {
    // We'll pass "today" to Odoo leaves and ProofHub time entries
    $today = now()->format('Y-m-d');

    $batch = Bus::batch([
      // Schedules fetch all, no date logic
      new SyncOdooSchedules(app(OdooApiCalls::class)),

      // Leaves => only fetch leaves overlapping today's date
      new SyncOdooLeaves(app(OdooApiCalls::class), $today, $today),

      // Projects & tasks always fetch everything
      new SyncProofhubProjects(app(ProofhubApiCalls::class)),
      new SyncProofhubTasks(app(ProofhubApiCalls::class)),

      // Time entries => only fetch today's entries
      new SyncProofhubTimeEntries(app(ProofhubApiCalls::class), $today, $today),
    ])
      ->name('sync-user-data')
      ->then(function (Batch $batch) {
        Log::info('User data synchronization batch completed successfully.', [
          'batch_id' => $batch->id,
        ]);
      })
      ->catch(function (Batch $batch, Throwable $e) {
        Log::error('User data synchronization batch failed.', [
          'batch_id' => $batch->id,
          'error' => $e->getMessage(),
        ]);
      })
      ->finally(function (Batch $batch) {
        Log::info('User data synchronization batch has finished.', [
          'batch_id' => $batch->id,
        ]);
      })
      ->dispatch();

    return response()->json([
      'message' => 'User data synchronization batch dispatched successfully.',
      'batch_id' => $batch->id,
    ]);
  }
}
