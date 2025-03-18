<?php

namespace App\Console\Commands;

use App\Http\Controllers\BatchController;
use App\Jobs\SyncOdooUsers;
use App\Jobs\SyncOdooDepartments;
use App\Jobs\SyncOdooCategories;
use App\Jobs\SyncOdooLeaveTypes;
use App\Jobs\SyncOdooSchedules;
use App\Jobs\SyncOdooLeaves;
use App\Jobs\SyncProofhubUsers;
use App\Jobs\SyncProofhubProjects;
use App\Jobs\SyncProofhubTasks;
use App\Jobs\SyncProofhubTimeEntries;
use App\Jobs\SyncDesktimeUsers;
use App\Jobs\SyncDesktimeAttendances;
use App\Services\OdooApiCalls;
use App\Services\ProofhubApiCalls;
use App\Services\DesktimeApiCalls;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Exception;

class SyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sync
        {platform? : Platform to sync (odoo, proofhub, desktime, all)}
        {type? : Type of data to sync (e.g., users, leaves, projects)}
        {--from= : Start date (Y-m-d) for date-based data}
        {--to= : End date (Y-m-d) for date-based data}
        {--user-id= : Specific user ID for applicable data types}';

    /**
     * The console command description.
     */
    protected $description = 'Synchronize data from external platforms';

    /**
     * Map of platforms and their data types with corresponding job classes
     */
    protected array $platformDataMap = [
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
    protected array $dateBasedTypes = [
        'odoo' => ['leaves'],
        'proofhub' => ['time-entries'],
        'desktime' => ['attendances'],
    ];

    /**
     * Data types that accept user ID parameter
     */
    protected array $userBasedTypes = [
        'desktime' => ['attendances'],
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $platform = $this->argument('platform');
        $type = $this->argument('type');
        
        // If no platform specified, show general help
        if (!$platform) {
            $this->showGeneralHelp();
            return 0;
        }

        // Handle 'all' platform option
        if ($platform === 'all') {
            return $this->syncAllPlatforms();
        }

        // Validate platform
        if (!isset($this->platformDataMap[$platform])) {
            $this->error("Unknown platform: $platform");
            $this->showGeneralHelp();
            return 1;
        }

        // If no type specified, sync the entire platform
        if (!$type) {
            return $this->syncPlatform($platform);
        }

        // Validate data type for platform
        if (!isset($this->platformDataMap[$platform][$type])) {
            $this->error("Unknown data type '$type' for platform '$platform'");
            $this->showPlatformHelp($platform);
            return 1;
        }

        // Sync specific data type
        return $this->syncDataType($platform, $type);
    }

    /**
     * Sync all platforms.
     */
    protected function syncAllPlatforms(): int
    {
        $this->info("Starting full synchronization...");
        
        try {
            $controller = new BatchController();
            $this->output->progressStart(3);
            $this->output->progressAdvance();
            
            $result = $controller->dispatchFullSyncBatch();
            $batchId = $result->getData()->batch_id;
            
            $this->output->progressAdvance();
            $this->info("✓ Full sync batch dispatched successfully");
            $this->line("  <comment>Batch ID:</comment> {$batchId}");
            $this->output->progressFinish();
            $this->line("\n<info>Batch jobs were dispatched to the queue and will run in the background.</info>");
            
            return 0;
        } catch (\Exception $e) {
            $this->output->progressFinish();
            $this->error("Failed to dispatch batch: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Sync all data for a specific platform.
     */
    protected function syncPlatform(string $platform): int
    {
        $this->info("Syncing all $platform data...");
        
        try {
            $controller = new BatchController();
            $methodName = "dispatch" . ucfirst($platform) . "Batch";
            
            if (method_exists($controller, $methodName)) {
                $result = $controller->$methodName();
                $batchId = $result->getData()->batch_id;
                $this->info("✓ $platform sync batch dispatched successfully");
                $this->line("  <comment>Batch ID:</comment> {$batchId}");
                $this->line("<info>Batch jobs were dispatched to the queue and will run in the background.</info>");
                return 0;
            } else {
                // Fallback if batch method doesn't exist
                $dataTypes = array_keys($this->platformDataMap[$platform]);
                foreach ($dataTypes as $dataType) {
                    $this->syncDataType($platform, $dataType);
                }
                return 0;
            }
        } catch (\Exception $e) {
            $this->error("Failed to sync $platform: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Sync a specific data type from a platform.
     */
    protected function syncDataType(string $platform, string $type): int
    {
        $fromDate = $this->option('from');
        $toDate = $this->option('to');
        $userId = $this->option('user-id');
        
        // Use Laravel's validator for date parameters
        if (isset($this->dateBasedTypes[$platform]) && in_array($type, $this->dateBasedTypes[$platform])) {
            $validator = validator([
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ], [
                'from_date' => 'nullable|date_format:Y-m-d',
                'to_date' => 'nullable|date_format:Y-m-d',
            ]);
            
            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $this->error($error);
                }
                return 1;
            }
        }
        
        // Check if user ID is applicable and validate it
        $hasUserIdOption = isset($this->userBasedTypes[$platform]) && in_array($type, $this->userBasedTypes[$platform]);
        
        if ($hasUserIdOption && $userId !== null) {
            $validator = validator([
                'user_id' => $userId,
            ], [
                'user_id' => 'integer|min:1',
            ]);
            
            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $this->error($error);
                }
                return 1;
            }
        }
        
        $this->info("Syncing $platform $type" . 
            ($hasUserIdOption && $userId ? " for user $userId" : "") .
            ($fromDate ? " from $fromDate" : "") . 
            ($toDate ? " to $toDate" : "") . "...");
        
        try {
            // Get API service
            $apiService = match($platform) {
                'odoo' => app(OdooApiCalls::class),
                'proofhub' => app(ProofhubApiCalls::class),
                'desktime' => app(DesktimeApiCalls::class),
                default => throw new \Exception("Unknown platform service: $platform"),
            };
            
            // Get job class
            $jobClass = $this->platformDataMap[$platform][$type];
            
            // Create job instance with appropriate parameters
            $job = null;
            if ($platform === 'odoo' && $type === 'leaves') {
                $job = new $jobClass($apiService, $fromDate, $toDate);
            } elseif ($platform === 'proofhub' && $type === 'time-entries') {
                $job = new $jobClass($apiService, $fromDate, $toDate);
            } elseif ($platform === 'desktime' && $type === 'attendances') {
                $job = new $jobClass($apiService, $userId, $fromDate, $toDate);
            } else {
                $job = new $jobClass($apiService);
            }
            
            // Dispatch job to a platform-specific queue
            dispatch($job)->onQueue("sync-{$platform}")->delay(now());
            
            $this->info("Job dispatched successfully to queue: sync-{$platform}. Check the queue for progress.");
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to sync $platform $type: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Show general help information.
     */
    protected function showGeneralHelp(): void
    {
        $this->info("Sync Command");
        $this->line("Synchronize data from external platforms");
        $this->line("");
        
        $this->info("Usage:");
        $this->line("  <info>php artisan sync {platform} {type} [options]</info>");
        $this->line("");
        
        $this->info("Platforms:");
        $this->line("  <info>all</info>       Sync all platforms");
        $this->line("  <info>odoo</info>      Sync Odoo data");
        $this->line("  <info>proofhub</info>  Sync ProofHub data");
        $this->line("  <info>desktime</info>  Sync DeskTime data");
        $this->line("");
        
        $this->info("Options:");
        $this->line("  <info>--from=</info>    Start date (Y-m-d) for date-based data");
        $this->line("  <info>--to=</info>      End date (Y-m-d) for date-based data");
        $this->line("  <info>--user-id=</info> Specific user ID for applicable data types");
        $this->line("");
        
        $this->info("Examples:");
        $this->line("  <info>php artisan sync all</info>");
        $this->line("  <info>php artisan sync odoo</info>");
        $this->line("  <info>php artisan sync odoo leaves --from=2023-01-01 --to=2023-12-31</info>");
        $this->line("  <info>php artisan sync proofhub time-entries --from=2023-01-01</info>");
        $this->line("  <info>php artisan sync desktime attendances --user-id=123</info>");
        $this->line("");
        
        $this->info("For platform-specific help:");
        $this->line("  <info>php artisan sync odoo</info> (without type argument)");
    }

    /**
     * Show platform-specific help information.
     */
    protected function showPlatformHelp(string $platform): void
    {
        if (!isset($this->platformDataMap[$platform])) {
            $this->error("Unknown platform: $platform");
            return;
        }
        
        $this->info("$platform Sync Options");
        $this->line("");
        
        $this->info("Available data types:");
        foreach (array_keys($this->platformDataMap[$platform]) as $type) {
            $this->line("  <info>$type</info>");
        }
        $this->line("");
        
        $this->info("Date parameters:");
        if (isset($this->dateBasedTypes[$platform])) {
            foreach ($this->dateBasedTypes[$platform] as $type) {
                $this->line("  <info>$type</info> accepts --from and --to date parameters");
            }
        } else {
            $this->line("  No data types accept date parameters");
        }
        $this->line("");
        
        $this->info("User ID parameters:");
        if (isset($this->userBasedTypes[$platform])) {
            foreach ($this->userBasedTypes[$platform] as $type) {
                $this->line("  <info>$type</info> accepts --user-id parameter");
            }
        } else {
            $this->line("  No data types accept user ID parameter");
        }
        $this->line("");
        
        $this->info("Examples:");
        switch ($platform) {
            case 'odoo':
                $this->line("  <info>php artisan sync odoo users</info>");
                $this->line("  <info>php artisan sync odoo leaves --from=2023-01-01 --to=2023-12-31</info>");
                break;
            case 'proofhub':
                $this->line("  <info>php artisan sync proofhub projects</info>");
                $this->line("  <info>php artisan sync proofhub time-entries --from=2023-01-01</info>");
                break;
            case 'desktime':
                $this->line("  <info>php artisan sync desktime users</info>");
                $this->line("  <info>php artisan sync desktime attendances --user-id=123 --from=2023-01-01</info>");
                break;
        }
    }

    /**
     * Validate a date string.
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

    /**
     * Dispatch a job for the specified platform and type
     */
    protected function dispatchJob(string $platform, string $type, array $options = []): int
    {
        try {
            // Determine which API service to use based on platform
            $apiService = match ($platform) {
                'odoo' => app(OdooApiCalls::class),
                'proofhub' => app(ProofhubApiCalls::class),
                'desktime' => app(DesktimeApiCalls::class),
                default => throw new Exception("Unknown platform: {$platform}")
            };
            
            // Create job instance based on platform and type
            $job = match ($platform) {
                'odoo' => match ($type) {
                    'all' => $this->dispatchAllOdooJobs(),
                    'users' => new SyncOdooUsers($apiService),
                    'leave-types' => new SyncOdooLeaveTypes($apiService),
                    'leaves' => new SyncOdooLeaves($apiService),
                    'schedules' => new SyncOdooSchedules($apiService),
                    default => throw new Exception("Unknown Odoo sync type: {$type}")
                },
                'proofhub' => match ($type) {
                    'all' => $this->dispatchAllProofhubJobs(),
                    'users' => new SyncProofhubUsers($apiService),
                    'projects' => new SyncProofhubProjects($apiService),
                    'tasks' => new SyncProofhubTasks($apiService),
                    'time-entries' => new SyncProofhubTimeEntries($apiService),
                    default => throw new Exception("Unknown ProofHub sync type: {$type}")
                },
                'desktime' => match ($type) {
                    'all' => $this->dispatchAllDesktimeJobs(),
                    'users' => new SyncDesktimeUsers($apiService),
                    'attendances' => new SyncDesktimeAttendances($apiService),
                    default => throw new Exception("Unknown DeskTime sync type: {$type}")
                },
                'all' => match ($type) {
                    'all' => $this->dispatchAllSyncJobs(),
                    default => throw new Exception("Only 'all' is a valid type when platform is 'all'")
                },
                default => throw new Exception("Unknown platform: {$platform}")
            };
            
            // If the job is an integer, it's a count of dispatched jobs
            if (is_int($job)) {
                return $job;
            }
            
            // Add the job to the queue for the specified platform
            $job->onQueue($platform);
            dispatch($job);
            
            Log::info("Successfully dispatched sync job", [
                'platform' => $platform,
                'type' => $type,
                'job' => get_class($job)
            ]);
            
            return 1; // One job dispatched
        } catch (Exception $e) {
            Log::error("Failed to dispatch sync job", [
                'platform' => $platform,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            
            $this->error("Error: {$e->getMessage()}");
            return 0; // No jobs dispatched
        }
    }

    /**
     * Dispatch all Odoo jobs
     */
    protected function dispatchAllOdooJobs(): int
    {
        Log::info('Dispatching all Odoo sync jobs');
        
        $count = 0;
        $count += $this->dispatchJob('odoo', 'users');
        $count += $this->dispatchJob('odoo', 'leave-types');
        $count += $this->dispatchJob('odoo', 'leaves');
        $count += $this->dispatchJob('odoo', 'schedules');
        
        return $count;
    }
    
    /**
     * Dispatch all ProofHub jobs
     */
    protected function dispatchAllProofhubJobs(): int
    {
        Log::info('Dispatching all ProofHub sync jobs');
        
        $count = 0;
        $count += $this->dispatchJob('proofhub', 'users');
        $count += $this->dispatchJob('proofhub', 'projects');
        $count += $this->dispatchJob('proofhub', 'tasks');
        $count += $this->dispatchJob('proofhub', 'time-entries');
        
        return $count;
    }
    
    /**
     * Dispatch all DeskTime jobs
     */
    protected function dispatchAllDesktimeJobs(): int
    {
        Log::info('Dispatching all DeskTime sync jobs');
        
        $count = 0;
        $count += $this->dispatchJob('desktime', 'users');
        $count += $this->dispatchJob('desktime', 'attendances');
        
        return $count;
    }
    
    /**
     * Dispatch all sync jobs for all platforms
     */
    protected function dispatchAllSyncJobs(): int
    {
        Log::info('Dispatching all sync jobs for all platforms');
        
        $count = 0;
        $count += $this->dispatchAllOdooJobs();
        $count += $this->dispatchAllProofhubJobs();
        $count += $this->dispatchAllDesktimeJobs();
        
        return $count;
    }
} 