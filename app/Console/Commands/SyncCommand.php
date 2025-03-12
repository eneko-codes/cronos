<?php

namespace App\Console\Commands;

use App\Jobs\{
  SyncDesktimeAttendances,
  SyncDesktimeUsers,
  SyncOdooCategories,
  SyncOdooDepartments,
  SyncOdooLeaves,
  SyncOdooLeaveTypes,
  SyncOdooSchedules,
  SyncOdooUsers,
  SyncProofhubProjects,
  SyncProofhubTasks,
  SyncProofhubTimeEntries,
  SyncProofhubUsers
};
use App\Services\OdooApiCalls;
use App\Services\DesktimeApiCalls;
use App\Services\ProofhubApiCalls;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncCommand extends Command
{
  /**
   * The name and signature of the console command.
   */
  protected $signature = 'sync
        {--odoo-users : Sync users from Odoo}
        {--odoo-departments : Sync departments from Odoo}
        {--odoo-categories : Sync categories from Odoo}
        {--odoo-schedules : Sync schedules from Odoo}
        {--odoo-leave-types : Sync leave types from Odoo}
        {--odoo-leaves : Sync leaves from Odoo}
        {--odoo-from= : Start date for Odoo leaves (Y-m-d)}
        {--odoo-to= : End date for Odoo leaves (Y-m-d)}

        {--proofhub-users : Sync users from ProofHub}
        {--proofhub-projects : Sync projects from ProofHub}
        {--proofhub-tasks : Sync tasks from ProofHub}
        {--proofhub-time-entries : Sync time entries from ProofHub}
        {--proofhub-from= : Start date for ProofHub time entries (Y-m-d)}
        {--proofhub-to= : End date for ProofHub time entries (Y-m-d)}

        {--desktime-users : Sync users from DeskTime}
        {--desktime-attendances : Sync attendance records from DeskTime}
        {--user-id= : Specific user ID for desktime-attendances}
        {--from= : Start date for desktime-attendances (Y-m-d)}
        {--to= : End date for desktime-attendances (Y-m-d)}';

  /**
   * The console command description.
   */
  protected $description = 'Run synchronization jobs for external services';

  /**
   * Mapping of command options to their corresponding job classes.
   *
   * @var array<string, string>
   */
  protected array $jobs = [
    // Odoo
    'odoo-users' => SyncOdooUsers::class,
    'odoo-departments' => SyncOdooDepartments::class,
    'odoo-categories' => SyncOdooCategories::class,
    'odoo-schedules' => SyncOdooSchedules::class,
    'odoo-leave-types' => SyncOdooLeaveTypes::class,
    'odoo-leaves' => SyncOdooLeaves::class,

    // ProofHub
    'proofhub-users' => SyncProofhubUsers::class,
    'proofhub-projects' => SyncProofhubProjects::class,
    'proofhub-tasks' => SyncProofhubTasks::class,
    'proofhub-time-entries' => SyncProofhubTimeEntries::class,

    // DeskTime
    'desktime-users' => SyncDesktimeUsers::class,
    'desktime-attendances' => SyncDesktimeAttendances::class,
  ];

  public function handle(): int
  {
    $dispatched = false;
    $anyOptionProvided = false;

    // Check if any sync option is provided
    foreach ($this->jobs as $option => $_) {
      if ($this->option($option)) {
        $anyOptionProvided = true;
        break;
      }
    }

    // If no options provided, run all jobs
    if (!$anyOptionProvided) {
      foreach ($this->jobs as $option => $jobClass) {
        switch ($option) {
          case 'desktime-attendances':
            dispatch(new SyncDesktimeAttendances(app(DesktimeApiCalls::class)));
            break;

          case 'proofhub-time-entries':
            dispatch(new SyncProofhubTimeEntries(app(ProofhubApiCalls::class)));
            break;

          case 'odoo-leaves':
            // No date range given in "run all" scenario => fetch all
            dispatch(new SyncOdooLeaves(app(OdooApiCalls::class)));
            break;

          default:
            dispatch(app($jobClass));
            break;
        }
        $this->info("Dispatched {$option} job");
        $dispatched = true;
      }
    } else {
      // Dispatch only the specified jobs
      foreach ($this->jobs as $option => $jobClass) {
        if ($this->option($option)) {
          switch ($option) {
            case 'desktime-attendances':
              // DeskTime-specific parameters
              $userId = $this->option('user-id');
              $fromDate = $this->option('from');
              $toDate = $this->option('to');

              if (
                ($fromDate && !$this->isValidDate($fromDate)) ||
                ($toDate && !$this->isValidDate($toDate))
              ) {
                $this->error(
                  'Invalid date format for --from or --to. Expected format: Y-m-d'
                );
                return 1;
              }

              dispatch(
                new SyncDesktimeAttendances(
                  app(DesktimeApiCalls::class),
                  $userId,
                  $fromDate,
                  $toDate
                )
              );
              $this->info(
                "Dispatched {$option} with user-id={$userId}, from={$fromDate}, to={$toDate}"
              );
              break;

            case 'proofhub-time-entries':
              // ProofHub time-entries parameters
              $proofhubFrom = $this->option('proofhub-from');
              $proofhubTo = $this->option('proofhub-to');

              if (
                ($proofhubFrom && !$this->isValidDate($proofhubFrom)) ||
                ($proofhubTo && !$this->isValidDate($proofhubTo))
              ) {
                $this->error(
                  'Invalid date format for --proofhub-from or --proofhub-to. Expected format: Y-m-d'
                );
                return 1;
              }

              dispatch(
                new SyncProofhubTimeEntries(
                  app(ProofhubApiCalls::class),
                  $proofhubFrom,
                  $proofhubTo
                )
              );
              $this->info(
                "Dispatched {$option} with from={$proofhubFrom}, to={$proofhubTo}"
              );
              break;

            case 'odoo-leaves':
              // Odoo leaves date range
              $odooFrom = $this->option('odoo-from');
              $odooTo = $this->option('odoo-to');

              if (
                ($odooFrom && !$this->isValidDate($odooFrom)) ||
                ($odooTo && !$this->isValidDate($odooTo))
              ) {
                $this->error(
                  'Invalid date format for --odoo-from or --odoo-to. Expected format: Y-m-d'
                );
                return 1;
              }

              dispatch(
                new SyncOdooLeaves(app(OdooApiCalls::class), $odooFrom, $odooTo)
              );
              $this->info(
                "Dispatched {$option} with from={$odooFrom}, to={$odooTo}"
              );
              break;

            default:
              // All other jobs without date logic
              dispatch(app($jobClass));
              $this->info("Dispatched {$option} job");
              break;
          }

          $dispatched = true;
        }
      }
    }

    if (!$dispatched) {
      $this->error('Failed to dispatch any jobs');
      return 1;
    }

    return 0;
  }

  /**
   * Validate the date format using Carbon.
   */
  protected function isValidDate(?string $date): bool
  {
    if (is_null($date)) {
      return false;
    }
    try {
      $parsed = Carbon::createFromFormat('Y-m-d', $date);
      return $parsed->format('Y-m-d') === $date;
    } catch (\Exception $e) {
      return false;
    }
  }
}
