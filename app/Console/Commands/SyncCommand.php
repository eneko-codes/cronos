<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\DispatchSyncBatchAction;
use Illuminate\Console\Command;

class SyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sync';

    /**
     * The console command description.
     */
    protected $description = 'Synchronize all data from external platforms';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        app(DispatchSyncBatchAction::class)();
        $this->info('✓ Full sync batch dispatched successfully');
        $this->line('<info>Batch jobs were dispatched to the queue and will run in the background.</info>');

        return 0;
    }
}
