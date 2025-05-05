<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Jobs\SyncDesktimeAttendances;
use App\Jobs\SyncDesktimeUsers;
use App\Jobs\SyncOdooLeaves;
use App\Jobs\SyncOdooSchedules;
use App\Jobs\SyncOdooUsers;
use App\Jobs\SyncProofhubProjects;
use App\Jobs\SyncProofhubTasks;
use App\Jobs\SyncProofhubTimeEntries;
use App\Jobs\SyncProofhubUsers;
use App\Models\JobBatch;
use App\Services\DesktimeApiService;
use App\Services\OdooApiService;
use App\Services\ProofhubApiService;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Throwable;

class SyncButton extends Component
{
    public bool $isLoading = false;

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

    public function sync(): void
    {
        if ($this->isLoading) {
            return;
        }
        $this->isLoading = true;
        $batchName = ''; // Initialize batchName

        try {
            // Define batch name first to ensure it's available in the catch block
            $batchName = $this->syncType === 'users' ? 'User synchronization' : 'User data synchronization';

            // Create service instances
            $odooService = app(OdooApiService::class);
            $desktimeService = app(DesktimeApiService::class);
            $proofhubService = app(ProofhubApiService::class);

            $jobs = $this->syncType === 'users' ? [
                new SyncOdooUsers($odooService),
                new SyncDesktimeUsers($desktimeService),
                new SyncProofhubUsers($proofhubService),
            ] : [
                new SyncOdooSchedules($odooService),
                new SyncDesktimeAttendances($desktimeService),
                new SyncOdooLeaves($odooService),
                new SyncProofhubProjects($proofhubService),
                new SyncProofhubTasks($proofhubService),
                new SyncProofhubTimeEntries(
                    $proofhubService,
                    now()->subDays(30)->format('Y-m-d'),
                    now()->format('Y-m-d')
                ),
            ];

            $this->total_jobs = count($jobs);

            $batch = Bus::batch($jobs)
                ->name($batchName)
                ->allowFailures()
                ->catch(function (Batch $batch, Throwable $e) use ($batchName) {
                    Log::error("{$batchName} failed.", ['batch_id' => $batch->id, 'error' => $e->getMessage()]);
                    JobBatch::where('id', $batch->id)->update([
                        'failed_jobs' => $batch->failedJobs,
                        'failed_job_ids' => $batch->failedJobIds,
                        'finished_at' => now()->timestamp,
                    ]);
                    $this->isLoading = false;
                    $this->dispatch('add-toast', message: "{$batchName} failed.", variant: 'error');
                })
                ->finally(function (Batch $batch) use ($batchName) {
                    JobBatch::where('id', $batch->id)->update([
                        'pending_jobs' => $batch->pendingJobs,
                        'finished_at' => $batch->finished() ? now()->timestamp : null,
                    ]);

                    if ($batch->finished()) {
                        $this->isLoading = false;
                        $this->dispatch('add-toast', message: "{$batchName} completed successfully.", variant: 'success');
                    } elseif ($batch->hasFailures()) {
                        $this->isLoading = false;
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
            $logMessage = $batchName ? "Failed to dispatch {$batchName}." : 'Failed to dispatch batch.'; // Use batchName if defined
            Log::error($logMessage, ['error' => $e->getMessage()]);
            $this->isLoading = false;
            $toastMessage = $batchName ? "Failed to start {$batchName}." : 'Failed to start sync.'; // Use batchName if defined
            $this->dispatch('add-toast', message: $toastMessage, variant: 'error');
        }
    }

    public function checkStatus(): void
    {
        if (! $this->batchId) {
            return;
        }

        $batch = JobBatch::find($this->batchId);

        if ($batch) {
            if ($batch->finished_at) {
                $this->isLoading = false;
                $this->dispatch('add-toast', message: "{$batch->name} completed successfully.", variant: 'success');
                $this->batchId = null;
            } elseif ($batch->failed_jobs > 0) {
                $this->isLoading = false;
                $this->dispatch('add-toast', message: "{$batch->name} failed.", variant: 'error');
                $this->batchId = null;
            } else {
                $this->completedJobs = $batch->total_jobs - $batch->pending_jobs - $batch->failed_jobs;
            }
        } else {
            $this->isLoading = false;
            $this->batchId = null;
        }
    }

    public function render()
    {
        return view('livewire.sync-button');
    }
}
