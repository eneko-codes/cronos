<?php

namespace App\Livewire;

use App\Models\JobBatch;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\Attributes\Lazy;

#[Lazy]
class LastSynced extends Component
{
    // Single status for the most recent sync
    public array $syncInfo = [
        'status' => 'unknown',
        'time' => 'Never synced'
    ];

    public function mount()
    {
        $this->loadSyncInfo();
    }

    /**
     * Format the relative time in a simplified way
     */
    protected function formatRelativeTime(Carbon $date): string 
    {
        $diff = $date->diff(now());
        
        if ($diff->y > 0) {
            return $diff->y . 'y ago';
        } else if ($diff->m > 0) {
            return $diff->m . 'mo ago';
        } else if ($diff->d > 0) {
            return $diff->d . 'd ago';
        } else if ($diff->h > 0) {
            return $diff->h . 'h ago';
        } else if ($diff->i > 0) {
            return $diff->i . 'm ago';
        } else {
            return 'just now';
        }
    }

    /**
     * Load the most recent sync information across all platforms
     */
    public function loadSyncInfo()
    {
        // Initialize with default values
        $this->syncInfo = [
            'status' => 'unknown',
            'time' => 'Never synced'
        ];

        // First check for any currently running jobs
        $runningBatch = JobBatch::whereNull('finished_at')
            ->where(function($query) {
                $query->where('name', 'User synchronization')
                    ->orWhere('name', 'User data synchronization');
            })
            ->orderByDesc('created_at')
            ->first();
            
        if ($runningBatch) {
            // If there's a sync in progress, show that
            $this->syncInfo = [
                'status' => 'in_progress',
                'time' => 'in progress'
            ];
            return;
        }
        
        // Get the most recent completed batch with flexible matching
        $latestBatch = JobBatch::whereNotNull('finished_at')
            ->where(function($query) {
                $query->where('name', 'like', '%sync%')
                    ->orWhere('name', 'like', '%Sync%');
            })
            ->orderByDesc('finished_at')
            ->first();
            
        if ($latestBatch) {
            $status = $latestBatch->failed_jobs > 0 ? 'error' : 'success';
            $this->syncInfo = [
                'status' => $status, 
                'time' => $this->formatRelativeTime($latestBatch->finished_at)
            ];
        }
    }

    public function render()
    {
        return view('livewire.last-synced');
    }
} 