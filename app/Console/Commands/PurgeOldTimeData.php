<?php

namespace App\Console\Commands;

use App\Models\DataRetentionSetting;
use App\Models\NotificationSetting;
use App\Models\TimeEntry;
use App\Models\UserAttendance;
use App\Models\UserLeave;
use App\Models\UserSchedule;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurgeOldTimeData extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'app:purge-old-time-data {--dry-run : Run without actually deleting data}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Delete old time-related data based on retention settings';

  /**
   * Execute the console command.
   */
  public function handle()
  {
    $isDryRun = $this->option('dry-run');
    $isEnabled = NotificationSetting::isEnabled('data_retention_enabled');

    if (!$isEnabled && !$isDryRun) {
      $this->info('Data retention policy is disabled. No data will be purged.');
      $this->info(
        'Use --dry-run to preview what would be deleted when enabled.'
      );
      return 0;
    }

    // Get the global retention period
    $retentionDays = $this->getGlobalRetentionPeriod();
    if ($retentionDays <= 0 && !$isDryRun) {
      $this->info(
        'Data retention is set to keep all data. No data will be purged.'
      );
      return 0;
    }

    if ($isDryRun) {
      $this->warn(
        'Running in DRY RUN mode - no data will actually be deleted.'
      );
    } else {
      $this->info('Starting data purge based on retention settings...');
      $this->info("Global retention period: {$retentionDays} days");
    }

    // Process each data type
    $this->purgeTimeEntries($isDryRun, $retentionDays);
    $this->purgeUserAttendances($isDryRun, $retentionDays);
    $this->purgeUserSchedules($isDryRun, $retentionDays);
    $this->purgeUserLeaves($isDryRun, $retentionDays);

    $this->info('Data purge process completed.');

    return 0;
  }

  /**
   * Get the global retention period from settings
   */
  private function getGlobalRetentionPeriod(): int
  {
    // Get retention period from any data type (they should all be the same)
    $setting = DataRetentionSetting::first();
    return $setting ? $setting->retention_days : 0;
  }

  /**
   * Purge time entries based on retention settings
   */
  private function purgeTimeEntries(bool $isDryRun, int $retentionDays): void
  {
    $this->info('Processing time entries...');

    if ($retentionDays <= 0) {
      $this->info('Time entries retention is set to keep all data.');
      return;
    }

    $cutoffDate = Carbon::now()->subDays($retentionDays)->startOfDay();
    $this->info(
      "Cutoff date: data before {$cutoffDate->format('Y-m-d')} will be deleted"
    );

    $query = TimeEntry::where('date', '<', $cutoffDate);
    $count = $query->count();

    $this->info("Found {$count} time entries to delete");

    if (!$isDryRun && $count > 0) {
      // Use chunk delete for better performance with large datasets
      $deleted = 0;
      $query->chunkById(
        1000,
        function ($entries) use (&$deleted) {
          foreach ($entries as $entry) {
            $entry->delete(); // Using delete() to trigger model events
            $deleted++;
          }
        },
        'proofhub_time_entry_id'
      );

      $this->info("Deleted {$deleted} time entries.");
      Log::info(
        "Data retention: Deleted {$deleted} time entries older than {$cutoffDate->format(
          'Y-m-d'
        )}"
      );
    }
  }

  /**
   * Purge user attendances based on retention settings
   */
  private function purgeUserAttendances(
    bool $isDryRun,
    int $retentionDays
  ): void {
    $this->info('Processing user attendances...');

    if ($retentionDays <= 0) {
      $this->info('User attendances retention is set to keep all data.');
      return;
    }

    $cutoffDate = Carbon::now()->subDays($retentionDays)->startOfDay();
    $this->info(
      "Cutoff date: data before {$cutoffDate->format('Y-m-d')} will be deleted"
    );

    $query = UserAttendance::where('date', '<', $cutoffDate);
    $count = $query->count();

    $this->info("Found {$count} attendance records to delete");

    if (!$isDryRun && $count > 0) {
      // Use chunk delete for better performance with large datasets
      $deleted = 0;
      $query->chunkById(1000, function ($records) use (&$deleted) {
        foreach ($records as $record) {
          $record->delete(); // Using delete() to trigger model events
          $deleted++;
        }
      });

      $this->info("Deleted {$deleted} attendance records.");
      Log::info(
        "Data retention: Deleted {$deleted} attendance records older than {$cutoffDate->format(
          'Y-m-d'
        )}"
      );
    }
  }

  /**
   * Purge user schedules based on retention settings
   */
  private function purgeUserSchedules(bool $isDryRun, int $retentionDays): void
  {
    $this->info('Processing user schedules...');

    if ($retentionDays <= 0) {
      $this->info('User schedules retention is set to keep all data.');
      return;
    }

    $cutoffDate = Carbon::now()->subDays($retentionDays)->startOfDay();
    $this->info(
      "Cutoff date: data before {$cutoffDate->format('Y-m-d')} will be deleted"
    );

    $query = UserSchedule::where('date', '<', $cutoffDate);
    $count = $query->count();

    $this->info("Found {$count} schedule records to delete");

    if (!$isDryRun && $count > 0) {
      // Use chunk delete for better performance with large datasets
      $deleted = 0;
      $query->chunkById(1000, function ($records) use (&$deleted) {
        foreach ($records as $record) {
          $record->delete(); // Using delete() to trigger model events
          $deleted++;
        }
      });

      $this->info("Deleted {$deleted} schedule records.");
      Log::info(
        "Data retention: Deleted {$deleted} schedule records older than {$cutoffDate->format(
          'Y-m-d'
        )}"
      );
    }
  }

  /**
   * Purge user leaves based on retention settings
   */
  private function purgeUserLeaves(bool $isDryRun, int $retentionDays): void
  {
    $this->info('Processing user leaves...');

    if ($retentionDays <= 0) {
      $this->info('User leaves retention is set to keep all data.');
      return;
    }

    $cutoffDate = Carbon::now()->subDays($retentionDays)->startOfDay();
    $this->info(
      "Cutoff date: data before {$cutoffDate->format('Y-m-d')} will be deleted"
    );

    $query = UserLeave::where('date', '<', $cutoffDate);
    $count = $query->count();

    $this->info("Found {$count} leave records to delete");

    if (!$isDryRun && $count > 0) {
      // Use chunk delete for better performance with large datasets
      $deleted = 0;
      $query->chunkById(1000, function ($records) use (&$deleted) {
        foreach ($records as $record) {
          $record->delete(); // Using delete() to trigger model events
          $deleted++;
        }
      });

      $this->info("Deleted {$deleted} leave records.");
      Log::info(
        "Data retention: Deleted {$deleted} leave records older than {$cutoffDate->format(
          'Y-m-d'
        )}"
      );
    }
  }
}
