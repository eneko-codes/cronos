<?php

namespace App\Console\Commands;

use App\Models\Alert;
use Illuminate\Console\Command;

class ClearAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clear-alerts {--type= : Clear only alerts of a specific type}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear alerts from the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->option('type');
        
        $query = Alert::query();
        
        if ($type) {
            $query->where('type', $type);
            $this->info("Clearing alerts of type: {$type}");
        } else {
            $this->info("Clearing all alerts");
        }
        
        $count = $query->count();
        
        if ($count === 0) {
            $this->info("No alerts found to clear.");
            return 0;
        }
        
        $query->delete();
        
        $this->info("Successfully cleared {$count} alert(s).");
        
        return 0;
    }
}
