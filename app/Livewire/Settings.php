<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Clients\DesktimeApiClient;
use App\Clients\OdooApiClient;
use App\Clients\ProofhubApiClient;
use App\Clients\SystemPinApiClient;
use App\Models\User;
use App\Services\ApplicationSettingsService;
use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Laravel\Telescope\Contracts\EntriesRepository;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Settings')]
#[Lazy]
class Settings extends Component
{
    public string $syncFrequency = 'everyThirtyMinutes';

    public bool $apiDownWarningEnabled = true;

    public bool $welcomeEmailEnabled = true;

    public bool $dataRetentionEnabled = false;

    public int $dataRetentionGlobalPeriod = 0;

    public bool $globalNotificationsEnabled = true;

    public bool $adminPromotionEmailEnabled = true;

    public bool $scheduleChangeEnabled = true;

    public bool $weeklyUserReportEnabled = true;

    public bool $leaveReminderEnabled = true;

    public bool $userPromotionNotificationEnabled = true;

    /** @var array<string, string> Stores the status of recent connection tests ('success', 'failed', 'pending') */
    public array $connectionStatus = [];

    private ApplicationSettingsService $settingsService;

    // Using boot for service initialization as mount can be re-triggered by Livewire
    // and we want to ensure the service is resolved once reliably.
    public function boot(): void
    {
        $this->settingsService = App::make(ApplicationSettingsService::class);
    }

    /**
     * Load existing settings from the database when the component mounts.
     */
    public function mount(): void
    {
        // $this->settingsService is already initialized by boot()
        $this->syncFrequency = $this->settingsService->getSyncFrequency();
        $this->apiDownWarningEnabled = $this->settingsService->getApiDownWarningEnabled();
        $this->welcomeEmailEnabled = $this->settingsService->getWelcomeEmailEnabled();
        $this->globalNotificationsEnabled = $this->settingsService->isGlobalNotificationsEnabled();
        $this->dataRetentionGlobalPeriod = $this->settingsService->getDataRetentionGlobalPeriod();
        $this->dataRetentionEnabled = $this->settingsService->isDataRetentionEnabled();
        $this->adminPromotionEmailEnabled = $this->settingsService->getAdminPromotionEmailEnabled();
        $this->userPromotionNotificationEnabled = $this->settingsService->getUserPromotionNotificationEnabled();

        // Load new global toggles using the service
        $this->scheduleChangeEnabled = $this->settingsService->isNotificationTypeGloballyEnabled('schedule_change');
        $this->weeklyUserReportEnabled = $this->settingsService->isNotificationTypeGloballyEnabled('weekly_user_report');
        $this->leaveReminderEnabled = $this->settingsService->isNotificationTypeGloballyEnabled('leave_reminder');

        // Ensure period is 0 if retention is disabled - this logic could also be part of the service setter
        if (! $this->dataRetentionEnabled) {
            $this->dataRetentionGlobalPeriod = 0;
        }
    }

    /**
     * Get the available frequency options for the data synchronization job.
     *
     * @return array<string, string> Key-value pairs of frequency slugs and labels.
     */
    public function getSyncFrequencyOptions(): array
    {
        return [
            'never' => 'Never',
            'everyMinute' => 'Every Minute',
            'everyFiveMinutes' => 'Every 5 Minutes',
            'everyFifteenMinutes' => 'Every 15 Minutes',
            'everyThirtyMinutes' => 'Every 30 Minutes',
            'hourly' => 'Every Hour',
            'everyTwoHours' => 'Every Two Hours',
            'everyThreeHours' => 'Every Three Hours',
            'everyFourHours' => 'Every Four Hours',
            'everySixHours' => 'Every Six Hours',
            'everyTwelveHours' => 'Every Twelve Hours',
            'dailyAt_9' => 'Daily at 9:00',
            'daily' => 'Daily at midnight',
            'weekly' => 'Weekly on Sunday',
            'twiceMonthly' => 'Twice Monthly (1st and 15th)',
            'monthly' => 'Monthly',
        ];
    }

    /**
     * Get the available data retention period options.
     *
     * @return array<int, string> Key-value pairs of retention days and labels.
     */
    public function getDataRetentionOptions(): array
    {
        return [
            0 => 'Disabled',
            30 => '30 days',
            90 => '3 months',
            180 => '6 months',
            365 => '1 year',
            730 => '2 years',
            1095 => '3 years',
            1825 => '5 years',
        ];
    }

    // --- Updated Lifecycle Hooks for Immediate Saving using ApplicationSettingsService --- //

    public function updatedSyncFrequency($value): void
    {
        $this->saveSettingViaService(
            'setSyncFrequency',
            $value,
            'Synchronization frequency updated.',
            'success'
        );
    }

    public function updatedGlobalNotificationsEnabled($value): void
    {
        $boolValue = (bool) $value;
        $message = $boolValue ? 'Global notifications enabled.' : 'Global notifications disabled.';
        $variant = $boolValue ? 'success' : 'info';

        $this->saveSettingViaService(
            'setGlobalNotificationsEnabled',
            $boolValue,
            $message,
            $variant,
            'global-notifications-updated',
            ['enabled' => $boolValue]
        );
    }

    public function updatedApiDownWarningEnabled($value): void
    {
        $boolValue = (bool) $value;
        $message = $boolValue ? 'Global "API Down Warning" notification enabled.' : 'Global "API Down Warning" notification disabled.';
        $variant = $boolValue ? 'success' : 'info';

        $this->saveSettingViaService(
            'setApiDownWarningEnabled',
            $boolValue,
            $message,
            $variant,
            'api-down-warning-global-setting-updated'
        );
    }

    public function updatedWelcomeEmailEnabled($value): void
    {
        $boolValue = (bool) $value;
        $message = $boolValue ? 'Welcome email enabled.' : 'Welcome email disabled.';
        $variant = $boolValue ? 'success' : 'info';

        $this->saveSettingViaService(
            'setWelcomeEmailEnabled',
            $boolValue,
            $message,
            $variant
        );
    }

    public function updatedDataRetentionGlobalPeriod($value): void
    {
        try {
            $this->settingsService->setDataRetentionSettings((int) $value);
            // Update component properties directly after successful save from the service's current state
            $this->dataRetentionGlobalPeriod = $this->settingsService->getDataRetentionGlobalPeriod();
            $this->dataRetentionEnabled = $this->settingsService->isDataRetentionEnabled();

            $this->dispatch(
                'add-toast',
                message: 'Data retention settings updated.',
                variant: 'success'
            );
        } catch (Exception $e) {
            $this->dispatch(
                'add-toast',
                message: 'Failed to update data retention settings: '.
                  $e->getMessage(),
                variant: 'error'
            );
            // Revert component state by re-fetching from the service
            $this->dataRetentionGlobalPeriod = $this->settingsService->getDataRetentionGlobalPeriod();
            $this->dataRetentionEnabled = $this->settingsService->isDataRetentionEnabled();
        }
    }

    public function updatedAdminPromotionEmailEnabled($value): void
    {
        $boolValue = (bool) $value;
        $message = $boolValue ? 'Admin promotion email enabled.' : 'Admin promotion email disabled.';
        $variant = $boolValue ? 'success' : 'info';

        $this->saveSettingViaService(
            'setAdminPromotionEmailEnabled',
            $boolValue,
            $message,
            $variant,
            'admin-promotion-email-global-setting-updated'
        );
    }

    public function updatedScheduleChangeEnabled($value): void
    {
        $boolValue = (bool) $value;
        $message = $boolValue ? 'Global "Schedule Change" notification enabled.' : 'Global "Schedule Change" notification disabled.';
        $variant = $boolValue ? 'success' : 'info';

        $this->saveSettingViaService(
            'setNotificationTypeGlobalState',
            ['schedule_change', $boolValue],
            $message,
            $variant,
            'schedule-change-global-setting-updated'
        );
    }

    public function updatedWeeklyUserReportEnabled($value): void
    {
        $boolValue = (bool) $value;
        $message = $boolValue ? 'Global "Weekly User Report" notification enabled.' : 'Global "Weekly User Report" notification disabled.';
        $variant = $boolValue ? 'success' : 'info';

        $this->saveSettingViaService(
            'setNotificationTypeGlobalState',
            ['weekly_user_report', $boolValue],
            $message,
            $variant,
            'weekly-user-report-global-setting-updated'
        );
    }

    public function updatedLeaveReminderEnabled($value): void
    {
        $boolValue = (bool) $value;
        $message = $boolValue ? 'Global "Leave Reminder" notification enabled.' : 'Global "Leave Reminder" notification disabled.';
        $variant = $boolValue ? 'success' : 'info';

        $this->saveSettingViaService(
            'setNotificationTypeGlobalState',
            ['leave_reminder', $boolValue],
            $message,
            $variant,
            'leave-reminder-global-setting-updated'
        );
    }

    // Add new updated hook
    public function updatedUserPromotionNotificationEnabled($value): void
    {
        $boolValue = (bool) $value;
        $message = $boolValue ? 'User promotion notification enabled.' : 'User promotion notification disabled.';
        $variant = $boolValue ? 'success' : 'info';

        $this->saveSettingViaService(
            'setUserPromotionNotificationEnabled',
            $boolValue,
            $message,
            $variant
            // No specific event dispatch needed for this one unless another component needs it
        );
    }

    /**
     * Helper method to save a single setting value via the service and dispatch feedback.
     *
     * @param  string  $serviceMethod  The method name on ApplicationSettingsService.
     * @param  mixed  $valueOrArgs  The value to save, or an array of arguments for the service method.
     * @param  string  $message  The dynamic message for the success toast.
     * @param  string  $variant  The dynamic variant for the success toast.
     * @param  string|null  $specificEventName  Optional specific event to dispatch for other components.
     * @param  array|null  $specificEventParams  Parameters for the specific event.
     */
    private function saveSettingViaService(
        string $serviceMethod,
        mixed $valueOrArgs,
        string $message,
        string $variant,
        ?string $specificEventName = null,
        ?array $specificEventParams = null
    ): void {
        try {
            if (is_array($valueOrArgs)) {
                // Use call_user_func_array if the service method expects multiple arguments
                call_user_func_array([$this->settingsService, $serviceMethod], $valueOrArgs);
            } else {
                // Directly call the method if it expects a single argument
                $this->settingsService->{$serviceMethod}($valueOrArgs);
            }

            $this->dispatch(
                'add-toast',
                message: $message,
                variant: $variant
            );

            if ($specificEventName) {
                // Determine the value for the event payload
                $eventValue = $specificEventParams['enabled'] ?? (is_array($valueOrArgs) ? $valueOrArgs[1] : $valueOrArgs);
                $this->dispatch($specificEventName, enabled: (bool) $eventValue);
            }

        } catch (Exception $e) {
            $this->dispatch(
                'add-toast',
                message: 'Failed to update setting: '.$e->getMessage(),
                variant: 'error'
            );
            // Revert the associated component property by reloading all settings via mount()
            $this->mount(); // This re-initializes all properties from the service
        }
    }

    /**
     * Test the API connection for a given platform.
     */
    private function testConnection(string $platform): void
    {
        $this->connectionStatus[$platform] = 'pending';
        $client = null;
        try {
            $client = match ($platform) {
                'odoo' => app(OdooApiClient::class),
                'desktime' => app(DesktimeApiClient::class),
                'proofhub' => app(ProofhubApiClient::class),
                'system-pin' => app(SystemPinApiClient::class),
                default => throw new Exception("Unknown platform: {$platform}"),
            };

            $result = $client->ping();
            if ($result['success']) {
                $this->connectionStatus[$platform] = 'success';
                $this->dispatch('add-toast', message: "Successfully connected to {$platform}.", variant: 'success');
            } else {
                $this->connectionStatus[$platform] = 'failed';
                $this->dispatch('add-toast', message: "Failed to connect to {$platform}: {$result['message']}", variant: 'error');
            }
        } catch (Exception $e) {
            $this->connectionStatus[$platform] = 'failed';
            $this->dispatch('add-toast', message: ucfirst($platform)." connection test failed: {$e->getMessage()}", variant: 'error'); // Improved error message
        }
    }

    public function pingOdoo(): void
    {
        $this->testConnection('odoo');
    }

    public function pingDesktime(): void
    {
        $this->testConnection('desktime');
    }

    public function pingProofhub(): void
    {
        $this->testConnection('proofhub');
    }

    public function pingSystemPin(): void
    {
        $this->testConnection('system-pin');
    }

    /**
     * Manually trigger the data retention artisan command.
     * Checks if data retention is enabled before running.
     */
    public function runDataRetention(): void
    {
        try {
            // Check the internal property which is updated by the hook
            if (
                ! $this->dataRetentionEnabled ||
                $this->dataRetentionGlobalPeriod <= 0
            ) {
                $this->dispatch(
                    'add-toast',
                    message: 'Data retention is disabled. Please enable it first.',
                    variant: 'error'
                );

                return;
            }

            $exitCode = Artisan::call('app:purge-old-time-data');

            if ($exitCode === 0) {
                $this->dispatch(
                    'add-toast',
                    message: 'Data retention process completed successfully.',
                    variant: 'success'
                );
            } else {
                $this->dispatch(
                    'add-toast',
                    message: 'Data retention process failed with exit code '.$exitCode,
                    variant: 'error'
                );
            }
        } catch (Exception $e) {
            $this->dispatch(
                'add-toast',
                message: 'Failed to run data retention: '.$e->getMessage(),
                variant: 'error'
            );
        }
    }

    /**
     * Render a skeleton placeholder while the settings component is loading.
     * This provides a visual indication that the application settings are being fetched.
     */
    /*
    public function placeholder()
    {
        return view('livewire.placeholders.settings');
    }*/

    /**
     * Render the settings component view.
     * Passes necessary data like options and counts to the view.
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        $totalUsers = User::count();
        // Count users whose preferences are not muted
        $activeUsers = User::whereHas('notificationPreferences', function ($query): void {
            $query->where('mute_all', false);
        })->count();

        // Count active admins whose preferences are not muted
        $activeAdmins = User::where('is_admin', true)
            ->whereHas('notificationPreferences', function ($query): void {
                $query->where('mute_all', false);
            })
            ->count();

        // Check if Telescope service is bound in the container (indicates installation)
        // and if the Telescope recording feature is enabled.
        $telescopeEnabled =
          app()->bound(EntriesRepository::class) && config('telescope.enabled');

        // Check if Pulse is enabled via its configuration.
        $pulseEnabled = config('pulse.enabled', false);

        return view('livewire.settings', [
            'syncFrequencyOptions' => $this->getSyncFrequencyOptions(),
            'dataRetentionOptions' => $this->getDataRetentionOptions(),
            'totalUsers' => $totalUsers,
            'activeUsers' => $activeUsers,
            'activeAdmins' => $activeAdmins,
            'telescopeEnabled' => $telescopeEnabled,
            'pulseEnabled' => $pulseEnabled,
        ]);
    }
}
