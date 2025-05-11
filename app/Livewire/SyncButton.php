<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\JobBatch;
use App\Services\SyncService;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Throwable;

#[Lazy]
class SyncButton extends Component
{
    public string $syncType;

    public ?string $batchId = null;

    public int $completedJobs = 0;

    public int $total_jobs = 0;

    public function mount(string $syncType): void
    {
        if (! in_array($syncType, ['users', 'data'])) {
            abort(404);
        }
        $this->syncType = $syncType;
    }

    public function sync(SyncService $syncService): void
    {
        $batchName = ''; // Initialize batchName

        try {
            // Define batch name first to ensure it's available in the catch block
            $batchName = $this->syncType === 'users' ? 'User synchronization' : 'User data synchronization';

            // Get jobs from the SyncService
            $jobs = $this->syncType === 'users'
                ? $syncService->getUsersSyncJobs()
                : $syncService->getDataSyncJobs();

            $this->total_jobs = count($jobs);

            $batch = Bus::batch($jobs)
                ->name($batchName)
                ->allowFailures()
                ->catch(function (Batch $batch, Throwable $e) use ($batchName): void {
                    Log::error("{$batchName} failed.", ['batch_id' => $batch->id, 'error' => $e->getMessage()]);
                    JobBatch::where('id', $batch->id)->update([
                        'failed_jobs' => $batch->failedJobs,
                        'failed_job_ids' => $batch->failedJobIds,
                        'finished_at' => now()->timestamp,
                    ]);
                    $this->dispatch('add-toast', message: "{$batchName} failed.", variant: 'error');
                })
                ->finally(function (Batch $batch) use ($batchName): void {
                    JobBatch::where('id', $batch->id)->update([
                        'pending_jobs' => $batch->pendingJobs,
                        'finished_at' => $batch->finished() ? now()->timestamp : null,
                    ]);

                    if ($batch->finished()) {
                        $this->dispatch('add-toast', message: "{$batchName} completed successfully.", variant: 'success');
                    } elseif ($batch->hasFailures()) {
                        $this->dispatch('add-toast', message: "{$batchName} has failures.", variant: 'error');
                    }
                })
                ->dispatch();

            $this->batchId = $batch->id;

            JobBatch::firstOrCreate(
                ['id' => $batch->id],
                [
                    'name' => $batchName,
                    'total_jobs' => count($jobs),
                    'pending_jobs' => count($jobs),
                    'failed_jobs' => 0,
                    'failed_job_ids' => [],
                    'options' => null,
                    'cancelled_at' => null,
                    'created_at' => now()->timestamp,
                    'finished_at' => null,
                ]
            );

            $this->dispatch('add-toast', message: "{$batchName} started successfully.", variant: 'info');
        } catch (Throwable $e) {
            $logMessage = $batchName ? "Failed to dispatch {$batchName}." : 'Failed to dispatch batch.';
            Log::error($logMessage, ['error' => $e->getMessage()]);
            $toastMessage = $batchName ? "Failed to start {$batchName}." : 'Failed to start sync.';
            $this->dispatch('add-toast', message: $toastMessage, variant: 'error');
        }
    }

    /**
     * Render a skeleton placeholder while the sync button component is loading.
     * This provides a visual indication that the sync status is being fetched.
     *
     * @return \Illuminate\View\View
     */
    /*
    public function placeholder()
    {
        return view('livewire.placeholders.sync-button');
    }*/

    public function render()
    {
        return view('livewire.sync-button');
    }
}
