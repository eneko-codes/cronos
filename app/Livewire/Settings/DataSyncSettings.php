<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Actions\UpdateDataRetentionPeriodAction;
use App\Actions\UpdateSyncFrequencyAction;
use App\Actions\UpdateSyncWindowDaysAction;
use App\Enums\DataRetentionPeriod;
use App\Enums\SyncFrequencyType;
use App\Enums\SyncWindowDays;
use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Livewire component for managing data synchronization settings.
 *
 * Allows admins to configure:
 * - Sync frequency
 * - Sync window (days of data to fetch)
 * - Data retention period
 */
class DataSyncSettings extends Component
{
    public string $syncFrequency = 'everyThirtyMinutes';

    public int $syncWindowDays = 1;

    public int $dataRetentionGlobalPeriod = 0;

    public function mount(): void
    {
        $this->syncFrequency = Setting::getValue('sync_frequency', 'everyThirtyMinutes');
        $this->syncWindowDays = (int) Setting::getValue('sync_window_days', 1);
        $this->dataRetentionGlobalPeriod = (int) Setting::getValue('data_retention.global_period', 0);
    }

    /**
     * Get sync frequency options.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function syncFrequencyOptions(): array
    {
        $options = [];
        foreach (SyncFrequencyType::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * Get sync window days options.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function syncWindowDaysOptions(): array
    {
        $options = [];
        foreach (SyncWindowDays::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * Get data retention options.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function dataRetentionOptions(): array
    {
        $options = [];
        foreach (DataRetentionPeriod::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * Handle sync frequency change.
     */
    public function updatedSyncFrequency(
        string $value,
        UpdateSyncFrequencyAction $updateSyncFrequency
    ): void {
        try {
            $enum = SyncFrequencyType::from($value);
            $updateSyncFrequency->execute($enum);
            $this->dispatch('add-toast', message: 'Sync frequency updated.', variant: 'success');
        } catch (\ValueError $e) {
            $this->dispatch('add-toast', message: 'Invalid sync frequency selected.', variant: 'error');
        } catch (Exception $e) {
            $this->dispatch('add-toast', message: 'Failed to update sync frequency: '.$e->getMessage(), variant: 'error');
        }
    }

    /**
     * Handle sync window days change.
     */
    public function updatedSyncWindowDays(
        int $value,
        UpdateSyncWindowDaysAction $updateSyncWindow
    ): void {
        try {
            $enum = SyncWindowDays::from($value);
            $updateSyncWindow->execute($enum);
            $this->dispatch('add-toast', message: 'Sync window updated.', variant: 'success');
        } catch (\ValueError $e) {
            $this->dispatch('add-toast', message: 'Invalid sync window selected.', variant: 'error');
        } catch (Exception $e) {
            $this->dispatch('add-toast', message: 'Failed to update sync window: '.$e->getMessage(), variant: 'error');
        }
    }

    /**
     * Handle data retention period change.
     */
    public function updatedDataRetentionGlobalPeriod(
        int $value,
        UpdateDataRetentionPeriodAction $updateDataRetention
    ): void {
        try {
            $enum = DataRetentionPeriod::from($value);
            $updateDataRetention->execute($enum);
            $this->dispatch('add-toast', message: 'Data retention period updated.', variant: 'success');
        } catch (\ValueError $e) {
            $this->dispatch('add-toast', message: 'Invalid data retention period selected.', variant: 'error');
        } catch (Exception $e) {
            $this->dispatch('add-toast', message: 'Failed to update data retention period: '.$e->getMessage(), variant: 'error');
        }
    }

    /**
     * Manually run data retention.
     */
    public function runDataRetention(): void
    {
        if ($this->dataRetentionGlobalPeriod <= 0) {
            $this->dispatch('add-toast', message: 'Data retention is disabled or period not set.', variant: 'warning');

            return;
        }

        try {
            Artisan::call('model:prune');
            $this->dispatch('add-toast', message: 'Data retention process has been started.', variant: 'success');
        } catch (Exception $e) {
            $this->dispatch('add-toast', message: "Failed to run data retention: {$e->getMessage()}", variant: 'error');
            Log::error("Failed to run data retention job: {$e->getMessage()}");
        }
    }

    public function render()
    {
        return view('livewire.settings.data-sync-settings');
    }
}
