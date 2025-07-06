<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\UpdateGlobalNotificationPreferencesAction;
use App\Clients\DesktimeApiClient;
use App\Clients\OdooApiClient;
use App\Clients\ProofhubApiClient;
use App\Clients\SystemPinApiClient;
use App\Enums\DataRetentionPeriod;
use App\Enums\NotificationType;
use App\Enums\RoleType;
use App\Enums\SyncFrequencyType;
use App\Enums\SyncWindowDays;
use App\Models\Setting;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Laravel\Telescope\Contracts\EntriesRepository;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Settings')]
#[Lazy]
class Settings extends Component
{
    public string $syncFrequency = 'everyThirtyMinutes';

    public bool $dataRetentionEnabled = false;

    public int $dataRetentionGlobalPeriod = 0;

    public int $syncWindowDays = 1;

    /** @var bool Whether all notifications are globally enabled */
    public bool $globalNotificationsEnabled = true;

    /**
     * Stores the state of global notification type toggles.
     * Key: notification type value, Value: enabled/disabled (bool)
     */
    public array $notificationStates = [];

    /** @var array<string, string> Stores the status of recent connection tests ('success', 'failed', 'pending') */
    public array $connectionStatus = [];

    public function mount(): void
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user) {
            $this->authorize('accessSettingsPage');
            $settings = app(\App\Actions\GetNotificationPreferencesAction::class)->execute($user);
            $this->globalNotificationsEnabled = $settings['global_enabled'];
            $this->notificationStates = $settings['global_types'];
        }
        $this->syncFrequency = Setting::getValue('sync_frequency', 'everyThirtyMinutes');
        $this->syncWindowDays = (int) Setting::getValue('sync_window_days', 1);
        $this->dataRetentionGlobalPeriod = (int) Setting::getValue('data_retention.global_period', 0);
    }

    public function getSyncFrequencyOptions(): array
    {
        $options = [];
        foreach (SyncFrequencyType::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public function getDataRetentionOptions(): array
    {
        $options = [];
        foreach (DataRetentionPeriod::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public function getSyncWindowDaysOptions(): array
    {
        $options = [];
        foreach (SyncWindowDays::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public function updatedSyncFrequency($value): void
    {
        try {
            $enum = SyncFrequencyType::from($value);
            app(\App\Actions\UpdateSyncFrequencyAction::class)->execute($enum);
            $this->dispatch('add-toast', message: 'Sync frequency updated.', variant: 'success');
        } catch (\ValueError $e) {
            $this->dispatch('add-toast', message: 'Invalid sync frequency selected.', variant: 'error');
        } catch (\Exception $e) {
            $this->dispatch('add-toast', message: 'Failed to update sync frequency: '.$e->getMessage(), variant: 'error');
        }
    }

    /**
     * Called when the global notifications master switch is toggled.
     */
    public function updatedGlobalNotificationsEnabled($value, UpdateGlobalNotificationPreferencesAction $updateGlobal): void
    {
        $boolValue = (bool) $value;
        $message = $boolValue ? 'Global notifications enabled.' : 'Global notifications disabled.';
        $variant = $boolValue ? 'success' : 'info';
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user) {
            try {
                $updateGlobal->toggleMaster($user, $boolValue);
                $this->dispatch('add-toast', message: $message, variant: $variant);
                $this->dispatch('global-notifications-updated', enabled: $boolValue);
            } catch (\Exception $e) {
                $this->dispatch('add-toast', message: 'Failed to update setting: '.$e->getMessage(), variant: 'error');
            }
        }
    }

    /**
     * Called when a per-type global notification toggle is changed.
     */
    public function updatedNotificationStates($value, $key, UpdateGlobalNotificationPreferencesAction $updateGlobal): void
    {
        $boolValue = (bool) $value;
        try {
            $notificationType = NotificationType::from($key);
            $message = $boolValue
                ? "Global '{$notificationType->label()}' notification enabled."
                : "Global '{$notificationType->label()}' notification disabled.";
            $variant = $boolValue ? 'success' : 'info';
            $user = \Illuminate\Support\Facades\Auth::user();
            if ($user) {
                $updateGlobal->toggleType($user, $notificationType, $boolValue);
                $this->dispatch('add-toast', message: $message, variant: $variant);
                $this->dispatch($key.'-global-setting-updated', enabled: $boolValue);
            }
        } catch (\ValueError $e) {
            $this->dispatch(
                'add-toast',
                message: "Invalid notification type key: {$key}",
                variant: 'error'
            );
        } catch (\Exception $e) {
            $this->dispatch('add-toast', message: 'Failed to update setting: '.$e->getMessage(), variant: 'error');
        }
    }

    public function updatedSyncWindowDays($value): void
    {
        try {
            $enum = SyncWindowDays::from((int) $value);
            app(\App\Actions\UpdateSyncWindowDaysAction::class)->execute($enum);
            $this->dispatch('add-toast', message: 'Sync window updated.', variant: 'success');
        } catch (\ValueError $e) {
            $this->dispatch('add-toast', message: 'Invalid sync window selected.', variant: 'error');
        } catch (\Exception $e) {
            $this->dispatch('add-toast', message: 'Failed to update sync window: '.$e->getMessage(), variant: 'error');
        }
    }

    public function updatedDataRetentionGlobalPeriod($value): void
    {
        try {
            $enum = DataRetentionPeriod::from((int) $value);
            app(\App\Actions\UpdateDataRetentionPeriodAction::class)->execute($enum);
            $this->dispatch('add-toast', message: 'Data retention period updated.', variant: 'success');
        } catch (\ValueError $e) {
            $this->dispatch('add-toast', message: 'Invalid data retention period selected.', variant: 'error');
        } catch (\Exception $e) {
            $this->dispatch('add-toast', message: 'Failed to update data retention period: '.$e->getMessage(), variant: 'error');
        }
    }

    private function testConnection(string $platform): void
    {
        $this->connectionStatus[$platform] = 'pending';
        try {
            $client = null;
            switch ($platform) {
                case 'Odoo':
                    $client = App::make(OdooApiClient::class);
                    break;
                case 'DeskTime':
                    $client = App::make(DesktimeApiClient::class);
                    break;
                case 'ProofHub':
                    $client = App::make(ProofhubApiClient::class);
                    break;
                case 'SystemPin':
                    $client = App::make(SystemPinApiClient::class);
                    break;
            }

            if ($client) {
                $result = $client->ping();
                $this->connectionStatus[$platform] = $result['success'] ? 'success' : 'failed';
            } else {
                $this->connectionStatus[$platform] = 'failed';
            }
        } catch (Exception $e) {
            $this->connectionStatus[$platform] = 'failed';
        }
    }

    public function pingOdoo(): void
    {
        $this->testConnection('Odoo');
        $this->dispatchPingToast('Odoo');
    }

    public function pingDesktime(): void
    {
        $this->testConnection('DeskTime');
        $this->dispatchPingToast('DeskTime');
    }

    public function pingProofhub(): void
    {
        $this->testConnection('ProofHub');
        $this->dispatchPingToast('ProofHub');
    }

    public function pingSystemPin(): void
    {
        $this->testConnection('SystemPin');
        $this->dispatchPingToast('SystemPin');
    }

    /**
     * Dispatch a toast notification with the result of the last ping for the given platform.
     */
    private function dispatchPingToast(string $platform): void
    {
        $status = $this->connectionStatus[$platform] ?? 'failed';
        if ($status === 'success') {
            $this->dispatch('add-toast', message: "$platform connection successful!", variant: 'success');
        } elseif ($status === 'pending') {
            $this->dispatch('add-toast', message: "$platform connection is pending...", variant: 'info');
        } else {
            $this->dispatch('add-toast', message: "$platform connection failed.", variant: 'error');
        }
    }

    public function runDataRetention(): void
    {
        if (! $this->dataRetentionEnabled || $this->dataRetentionGlobalPeriod <= 0) {
            $this->dispatch(
                'add-toast',
                message: 'Data retention is disabled or period not set.',
                variant: 'warning'
            );

            return;
        }

        try {
            Artisan::call('model:prune');
            $this->dispatch(
                'add-toast',
                message: 'Data retention process has been started.',
                variant: 'success'
            );
        } catch (Exception $e) {
            $this->dispatch(
                'add-toast',
                message: "Failed to run data retention: {$e->getMessage()}",
                variant: 'error'
            );
            Log::error("Failed to run data retention job: {$e->getMessage()}");
        }
    }

    public function placeholder(array $params = [])
    {
        return view('livewire.placeholders.settings-skeleton', $params);
    }

    public function render()
    {
        $totalUsers = User::count();
        $activeUsers = User::where('muted_notifications', false)->count();

        $activeAdmins = User::where('user_type', RoleType::Admin)
            ->where('muted_notifications', false)
            ->count();

        $telescopeEnabled =
          app()->bound(EntriesRepository::class) && config('telescope.enabled');

        $pulseEnabled = config('pulse.enabled', false);

        return view('livewire.settings', [
            'syncFrequencyOptions' => $this->getSyncFrequencyOptions(),
            'dataRetentionOptions' => $this->getDataRetentionOptions(),
            'totalUsers' => $totalUsers,
            'activeUsers' => $activeUsers,
            'activeAdmins' => $activeAdmins,
            'telescopeEnabled' => $telescopeEnabled,
            'pulseEnabled' => $pulseEnabled,
            'notificationTypes' => NotificationType::cases(),
        ]);
    }
}
