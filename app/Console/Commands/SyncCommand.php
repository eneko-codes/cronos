<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

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
     * Execute the console command.
     */
    public function handle(SyncService $syncService): int
    {
        $platform = $this->argument('platform');
        $type = $this->argument('type');

        // If no platform specified, show general help
        if (! $platform) {
            $this->showGeneralHelp($syncService);

            return 0;
        }

        // Handle 'all' platform option
        if ($platform === 'all') {
            return $this->syncAllPlatforms($syncService);
        }

        // Validate platform
        if (! isset($syncService->platformDataMap[$platform])) {
            $this->error("Unknown platform: $platform");
            $this->showGeneralHelp($syncService);

            return 1;
        }

        // Handle platform-specific batch dispatch if type is 'all'
        if ($type === 'all') {
            return $this->syncPlatform($platform, $syncService);
        }

        // If no type specified, sync the entire platform OR show platform help
        if (! $type) {
            $this->showPlatformHelp($platform, $syncService);

            return 0; // Successful exit after showing help
        }

        // Validate data type for platform
        if (! isset($syncService->platformDataMap[$platform][$type])) {
            $this->error("Unknown data type '$type' for platform '$platform'");
            $this->showPlatformHelp($platform, $syncService);

            return 1;
        }

        // Determine if type accepts date/user parameters based on SyncService info
        $isDateBased = isset($syncService->dateBasedTypes[$platform]) && in_array($type, $syncService->dateBasedTypes[$platform]);
        $isUserBased = isset($syncService->userBasedTypes[$platform]) && in_array($type, $syncService->userBasedTypes[$platform]);

        // Sync specific data type
        return $this->syncDataType($platform, $type, $syncService, $isDateBased, $isUserBased);
    }

    /**
     * Sync all platforms.
     */
    protected function syncAllPlatforms(SyncService $syncService): int
    {
        $this->info('Starting full synchronization...');

        try {
            $batchId = $syncService->dispatchFullSyncBatch();

            $this->info('✓ Full sync batch dispatched successfully');
            $this->line("  <comment>Batch ID:</comment> {$batchId}");
            $this->line(
                '<info>Batch jobs were dispatched to the queue and will run in the background.</info>'
            );

            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to dispatch batch: '.$e->getMessage());

            return 1;
        }
    }

    /**
     * Sync all data for a specific platform.
     */
    protected function syncPlatform(string $platform, SyncService $syncService): int
    {
        $this->info("Syncing all $platform data...");

        try {
            $methodName = match ($platform) {
                'odoo' => 'dispatchOdooBatch',
                'proofhub' => 'dispatchProofhubBatch',
                'desktime' => 'dispatchDesktimeBatch',
                default => null,
            };

            if ($methodName) {
                $batchId = $syncService->$methodName();
                $this->info("✓ $platform sync batch dispatched successfully");
                $this->line("  <comment>Batch ID:</comment> {$batchId}");
                $this->line(
                    '<info>Batch jobs were dispatched to the queue and will run in the background.</info>'
                );

                return 0;
            } else {
                $this->error("No batch dispatch method found for platform: $platform");

                return 1;
            }
        } catch (\Exception $e) {
            $this->error("Failed to sync $platform: ".$e->getMessage());

            return 1;
        }
    }

    /**
     * Sync a specific data type from a platform.
     */
    protected function syncDataType(
        string $platform,
        string $type,
        SyncService $syncService,
        bool $isDateBased,
        bool $isUserBased
    ): int {
        $fromDate = $this->option('from');
        $toDate = $this->option('to');
        $userId = $this->option('user-id');

        // Use Laravel's validator for date parameters
        if ($isDateBased) {
            $validator = Validator::make(
                [
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                ],
                [
                    'from_date' => 'nullable|date_format:Y-m-d',
                    'to_date' => 'nullable|date_format:Y-m-d',
                ]
            );

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $this->error($error);
                }

                return 1;
            }
        }

        // Validate user ID if applicable and provided
        if ($isUserBased && $userId !== null) {
            $validator = Validator::make(
                [
                    'user_id' => $userId,
                ],
                [
                    'user_id' => 'integer|min:1',
                ]
            );

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $this->error($error);
                }

                return 1;
            }
        }

        // Prepare info message parts
        $infoParts = ["Syncing $platform $type"];
        if ($isUserBased && $userId) {
            $infoParts[] = "for user $userId";
        }
        if ($isDateBased && $fromDate) {
            $infoParts[] = "from $fromDate";
        }
        if ($isDateBased && $toDate) {
            $infoParts[] = "to $toDate";
        }
        $infoParts[] = '...';

        $this->info(
            implode(' ', $infoParts)
        );

        try {
            // Use SyncService to dispatch the single job
            $syncService->dispatchSingleJob($platform, $type, $userId, $fromDate, $toDate);

            $this->info(
                "✓ $platform $type sync job dispatched successfully. Check the queue for progress."
            );

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to sync $platform $type: ".$e->getMessage());

            return 1;
        }
    }

    /**
     * Show general help information.
     */
    protected function showGeneralHelp(?SyncService $syncService = null): void
    {
        // Fetch service if not provided (e.g., when validation fails early)
        $syncService = $syncService ?? app(SyncService::class);

        $this->info('Sync Command');
        $this->line('Synchronize data from external platforms');
        $this->line('');

        $this->info('Usage:');
        $this->line('  <info>php artisan sync {platform} {type} [options]</info>');
        $this->line('');

        $this->info('Platforms:');
        $this->line('  <info>all</info>       Sync all platforms');
        foreach (array_keys($syncService->platformDataMap) as $platformKey) {
            $this->line("  <info>{$platformKey}</info>      Sync ".ucfirst($platformKey).' data');
        }
        $this->line('');

        $this->info('Options:');
        $this->line(
            '  <info>--from=</info>    Start date (Y-m-d) for date-based data'
        );
        $this->line(
            '  <info>--to=</info>      End date (Y-m-d) for date-based data'
        );
        $this->line(
            '  <info>--user-id=</info> Specific user ID for applicable data types'
        );
        $this->line('');

        $this->info('Examples:');
        $this->line('  <info>php artisan sync all</info>');
        $this->line('  <info>php artisan sync odoo</info>');
        $this->line(
            '  <info>php artisan sync odoo leaves --from=2023-01-01 --to=2023-12-31</info>'
        );
        $this->line(
            '  <info>php artisan sync proofhub time-entries --from=2023-01-01</info>'
        );
        $this->line(
            '  <info>php artisan sync desktime attendances --user-id=123</info>'
        );
        $this->line('');

        $this->info('For platform-specific help:');
        $this->line('  <info>php artisan sync odoo</info> (without type argument)');
    }

    /**
     * Show platform-specific help information.
     */
    protected function showPlatformHelp(string $platform, SyncService $syncService): void
    {
        if (! isset($syncService->platformDataMap[$platform])) {
            $this->error("Unknown platform: $platform");

            return;
        }

        $this->info(ucfirst($platform).' Sync Options');
        $this->line('');

        $this->info('Available data types:');
        $this->line('  <info>all</info>       Sync all data types for '.ucfirst($platform));
        foreach (array_keys($syncService->platformDataMap[$platform]) as $type) {
            $this->line("  <info>$type</info>");
        }
        $this->line('');

        $this->info('Date parameters:');
        if (! empty($syncService->dateBasedTypes[$platform])) {
            foreach ($syncService->dateBasedTypes[$platform] as $type) {
                $this->line(
                    "  <info>$type</info> accepts --from and --to date parameters"
                );
            }
        } else {
            $this->line('  No data types accept date parameters');
        }
        $this->line('');

        $this->info('User ID parameters:');
        if (! empty($syncService->userBasedTypes[$platform])) {
            foreach ($syncService->userBasedTypes[$platform] as $type) {
                $this->line("  <info>$type</info> accepts --user-id parameter");
            }
        } else {
            $this->line('  No data types accept user ID parameter');
        }
        $this->line('');

        $this->info('Examples:');
        switch ($platform) {
            case 'odoo':
                $this->line('  <info>php artisan sync odoo all</info>');
                $this->line('  <info>php artisan sync odoo users</info>');
                $this->line(
                    '  <info>php artisan sync odoo leaves --from=2023-01-01 --to=2023-12-31</info>'
                );
                break;
            case 'proofhub':
                $this->line('  <info>php artisan sync proofhub all</info>');
                $this->line('  <info>php artisan sync proofhub projects</info>');
                $this->line(
                    '  <info>php artisan sync proofhub time-entries --from=2023-01-01</info>'
                );
                break;
            case 'desktime':
                $this->line('  <info>php artisan sync desktime all</info>');
                $this->line('  <info>php artisan sync desktime users</info>');
                $this->line(
                    '  <info>php artisan sync desktime attendances --user-id=123 --from=2023-01-01</info>'
                );
                break;
        }
    }
}
