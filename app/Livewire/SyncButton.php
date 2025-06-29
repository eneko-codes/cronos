<?php

declare(strict_types=1);

namespace App\Livewire;

use Livewire\Attributes\Lazy;
use Livewire\Component;

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

    public function sync(): void
    {
        app(\App\Actions\DispatchSyncBatchAction::class)();
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
