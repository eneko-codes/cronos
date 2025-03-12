<?php

namespace App\Console\Commands;

use App\Models\Alert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CleanupAlertsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alerts:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup all existing alerts and resync with notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Cleaning up alerts...');
        
        // Count alerts before cleanup
        $beforeCount = Alert::count();
        $this->info("Found {$beforeCount} alerts before cleanup");
        
        // Delete all alerts using query builder rather than truncate to avoid foreign key issues
        Alert::where('id', '>', 0)->delete();
        $this->info('All alerts have been removed');
        
        // Check if notifications table exists
        if (Schema::hasTable('notifications')) {
            // Sync with notifications
            $syncCount = Alert::syncUnreadNotifications();
            $this->info("Synced {$syncCount} alerts from unread notifications");
        } else {
            $this->warn('Notifications table does not exist. Run migrations to create it.');
            $this->info('Creating notifications table migration...');
            $this->call('notifications:table');
            $this->info('Please run php artisan migrate to create the notifications table');
        }
        
        // Count alerts after cleanup
        $afterCount = Alert::count();
        $this->info("Now have {$afterCount} alerts after cleanup");
        
        return Command::SUCCESS;
    }
}
