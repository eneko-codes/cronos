<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DetectScheduleDuplicates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:detect-schedule-duplicates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detects duplicate schedule details in existing schedule data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking schedules for duplicate details...');
        
        $schedules = Schedule::with('scheduleDetails')->get();
        $duplicatesFound = 0;
        
        foreach ($schedules as $schedule) {
            $duplicates = $this->detectDuplicateDetails($schedule);
            
            if ($duplicates->isNotEmpty()) {
                $duplicatesFound++;
                
                // Log warning about duplicates
                Log::warning("Schedule #{$schedule->odoo_schedule_id} ({$schedule->description}) has duplicate details", [
                    'schedule_id' => $schedule->odoo_schedule_id,
                    'duplicates' => $duplicates->toArray(),
                ]);
                
                // Create an alert for administrators
                Alert::createScheduleDuplicateAlert([
                    'schedule_id' => $schedule->odoo_schedule_id,
                    'schedule_name' => $schedule->description,
                    'duplicates' => $duplicates->toArray(),
                    'detected_at' => Carbon::now()->toIso8601String(),
                ]);
                
                // Show details in console
                $this->warn("Schedule #{$schedule->odoo_schedule_id} ({$schedule->description}) has duplicate details:");
                foreach ($duplicates as $duplicate) {
                    $this->line("  - Day {$duplicate['weekday']}, {$duplicate['day_period']}: {$duplicate['count']} duplicates");
                }
            }
        }
        
        if ($duplicatesFound === 0) {
            $this->info('No duplicate schedule details found.');
        } else {
            $this->warn("Found duplicate details in {$duplicatesFound} schedule(s).");
            $this->info('Alerts have been created for administrators to review.');
        }
        
        return 0;
    }
    
    /**
     * Detect duplicate details in a schedule.
     * 
     * @param Schedule $schedule
     * @return Collection
     */
    protected function detectDuplicateDetails(Schedule $schedule): Collection 
    {
        $duplicates = collect();
        
        // Make sure we have the details
        $details = $schedule->scheduleDetails;
        
        if ($details->isEmpty()) {
            return $duplicates;
        }
        
        // Group details by weekday and day_period
        $groupedDetails = $details->groupBy(function ($detail) {
            return $detail->weekday . '-' . $detail->day_period;
        });
        
        // Find groups with more than one entry
        foreach ($groupedDetails as $key => $group) {
            if ($group->count() > 1) {
                list($weekday, $dayPeriod) = explode('-', $key);
                
                $duplicates->push([
                    'weekday' => $weekday,
                    'day_period' => $dayPeriod,
                    'count' => $group->count(),
                ]);
            }
        }
        
        return $duplicates;
    }
}
