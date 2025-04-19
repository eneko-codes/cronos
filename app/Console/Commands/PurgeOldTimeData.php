<?php

namespace App\Console\Commands;

use App\Models\Setting;
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

    // Check if data retention is enabled using the new Setting model
    $isEnabled = (bool) Setting::getValue('data_retention.enabled', false);

    if (!$isEnabled && !$isDryRun) {
      $this->info('Data retention policy is disabled. No data will be purged.');
      $this->info(
        'Use --dry-run to preview what would be deleted when enabled.'
      );
      return 0;
    }

    // Get the global retention period using the new Setting model
    $retentionDays = (int) Setting::getValue('data_retention.global_period', 0);

    if ($retentionDays <= 0 && !$isDryRun) {
      $this->info(
        'Data retention period is not set or is zero. No data will be purged.'
      );
      return 0;
    }

    if ($isDryRun) {
      $this->warn(
        'Running in DRY RUN mode - no data will actually be deleted.'
      );
      if ($retentionDays <= 0) {
        $this->warn(
          'Current retention period is 0 days (or not set), so no data would be deleted even if enabled.'
        );
      } else {
        $this->info(
          "Effective retention period for dry run: {$retentionDays} days"
        );
      }
    } else {
      $this->info('Starting data purge based on retention settings...');
      $this->info("Global retention period: {$retentionDays} days");
    }

    // Process each data type
    $this->purgeModelData(
      TimeEntry::class,
      $isDryRun,
      $retentionDays,
      'time entries',
      'date'
    );
    $this->purgeModelData(
      UserAttendance::class,
      $isDryRun,
      $retentionDays,
      'attendance records',
      'date'
    );
    $this->purgeModelData(
      UserSchedule::class,
      $isDryRun,
      $retentionDays,
      'schedule records',
      'date'
    );
    $this->purgeModelData(
      UserLeave::class,
      $isDryRun,
      $retentionDays,
      'leave records',
      'date'
    ); // Assuming UserLeave also uses a 'date' column

    $this->info('Data purge process completed.');

    return 0;
  }

  /**
   * Generic method to purge old data for a given model and date column.
   *
   * @param string $modelClass The Eloquent model class name.
   * @param bool $isDryRun Whether to perform a dry run.
   * @param int $retentionDays The retention period in days.
   * @param string $dataTypeLabel A label for the data type being purged (for logging).
   * @param string $dateColumn The name of the date column to check against.
   */
  private function purgeModelData(
    string $modelClass,
    bool $isDryRun,
    int $retentionDays,
    string $dataTypeLabel,
    string $dateColumn = 'date'
  ): void {
    $this->line("\nProcessing {$dataTypeLabel}..."); // Use line for better spacing

    if ($retentionDays <= 0) {
      $this->info("{$dataTypeLabel} retention is set to keep all data.");
      return;
    }

    $cutoffDate = Carbon::now()->subDays($retentionDays)->startOfDay();
    $this->info(
      "Cutoff date: {$dataTypeLabel} before {$cutoffDate->format(
        'Y-m-d'
      )} will be deleted"
    );

    try {
      /** @var \Illuminate\Database\Eloquent\Builder $query */
      $query = $modelClass::where($dateColumn, '<', $cutoffDate);
      $count = $query->count();

      $this->info("Found {$count} {$dataTypeLabel} to delete");

      if (!$isDryRun && $count > 0) {
        // Use chunk delete for better performance with large datasets
        $deleted = 0;
        // Determine the primary key for chunking if not default 'id'
        $primaryKey = (new $modelClass())->getKeyName();

        $query->chunkById(
          1000,
          function ($records) use (&$deleted) {
            foreach ($records as $record) {
              $record->delete(); // Using delete() to trigger model events
              $deleted++;
            }
          },
          $primaryKey
        );

        $this->info("Deleted {$deleted} {$dataTypeLabel}.");
        Log::info(
          "Data retention: Deleted {$deleted} {$dataTypeLabel} older than {$cutoffDate->format(
            'Y-m-d'
          )}"
        );
      } elseif ($isDryRun) {
        $this->info("Dry run: No {$dataTypeLabel} were deleted.");
      }
    } catch (\Exception $e) {
      $this->error("Error processing {$dataTypeLabel}: " . $e->getMessage());
      Log::error("Data retention failed for {$dataTypeLabel}", [
        'exception' => $e,
        'trace' => $e->getTraceAsString(),
      ]);
    }
  }
}
