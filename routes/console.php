<?php

use App\Jobs\{
  SyncDesktimeAttendances,
  SyncDesktimeUsers,
  SyncOdooLeaves,
  SyncOdooLeaveTypes,
  SyncOdooSchedules,
  SyncOdooUsers,
  SyncOdooDepartments,
  SyncOdooCategories,
  SyncProofhubProjects,
  SyncProofhubTimeEntries,
  SyncProofhubUsers
};
use App\Models\JobFrequency;
use App\Services\{OdooApiCalls, DesktimeApiCalls, ProofhubApiCalls};
use Illuminate\Support\Facades\{Log, Schedule, Schema, Bus};

/**
 * Schedule the 'telescope:prune' command to run daily and clean records (they stack up fast!).
 */
Schedule::command('telescope:prune --hours=168')->daily();
/**
 * Schedule the 'queue:prune-batches' command to run daily and clean batched jobs.
 */
Schedule::command('queue:prune-batches --hours=48')->daily();
/**
 * Schedule the 'queue:prune-failed' command to run daily and clean failed jobs.
 */
Schedule::command('queue:prune-failed --hours=48')->daily();

// Define all jobs in sequence
$jobs = [
  // User info jobs first
  SyncOdooUsers::class,
  SyncOdooDepartments::class,
  SyncOdooCategories::class,
  SyncOdooLeaveTypes::class,
  SyncDesktimeUsers::class,
  SyncProofhubUsers::class,
  // Data jobs after user info is synced
  SyncOdooSchedules::class,
  SyncProofhubProjects::class,
  SyncOdooLeaves::class,
  SyncProofhubTimeEntries::class,
  SyncDesktimeAttendances::class,
];

try {
  if (Schema::hasTable('job_frequencies')) {
    $config = JobFrequency::getConfig();
    if ($config->frequency !== 'never') {
      $batchJobs = [];

      foreach ($jobs as $jobClass) {
        $job = match ($jobClass) {
          SyncOdooUsers::class,
          SyncOdooDepartments::class,
          SyncOdooCategories::class,
          SyncOdooLeaveTypes::class,
          SyncOdooSchedules::class,
          SyncOdooLeaves::class
            => new $jobClass(app(OdooApiCalls::class)),

          SyncDesktimeUsers::class,
          SyncDesktimeAttendances::class
            => new $jobClass(app(DesktimeApiCalls::class)),

          SyncProofhubUsers::class,
          SyncProofhubProjects::class,
          SyncProofhubTimeEntries::class
            => new $jobClass(app(ProofhubApiCalls::class)),

          default => throw new Exception("Unknown job class: {$jobClass}"),
        };

        $batchJobs[] = $job;
      }

      if (!empty($batchJobs)) {
        $scheduleMethod = $config->getScheduleMethod();
        if ($scheduleMethod) {
          $scheduleMethod(
            Schedule::job(function () use ($batchJobs) {
              Bus::batch($batchJobs)
                ->name('Daily data sync')
                ->then(function ($batch) {
                  Log::info('Daily data sync batch completed successfully');
                })
                ->catch(function ($batch, $e) {
                  Log::error(
                    "Daily data sync batch failed: {$e->getMessage()}"
                  );
                })
                ->dispatch();
            })
          );
        }
      }
    }
  }
} catch (Exception $e) {
  Log::error('Failed to schedule sync batch: ' . $e->getMessage());
}
