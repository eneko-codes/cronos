<?php

namespace App\Livewire;

use App\Models\Setting;
use App\Models\User;
use App\Services\DesktimeApiCalls;
use App\Services\OdooApiCalls;
use App\Services\ProofhubApiCalls;
use App\Services\SystemPinApiCalls;
use Exception;
use Laravel\Telescope\Contracts\EntriesRepository;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Facades\Artisan;

#[Title('Settings')]
class Settings extends Component
{
  // Settings properties - using keys from the new Setting model
  public string $syncFrequency = 'everyThirtyMinutes';
  public bool $apiDownWarningMailEnabled = true;
  public bool $welcomeEmailEnabled = true;
  public bool $dataRetentionEnabled = false;
  public int $dataRetentionGlobalPeriod = 0; // Stores the global retention period in days
  public string $telescopePruneFrequency = 'weekly';
  public bool $globalNotificationsEnabled = true; // Added
  public bool $adminPromotionEmailEnabled = true; // Added for admin promotion email

  /**
   * Load existing settings from the database when the component mounts.
   */
  public function mount(): void
  {
    // Load settings from the new Setting model
    $this->syncFrequency = Setting::getValue(
      'job_frequency.sync',
      'everyThirtyMinutes'
    );
    $this->apiDownWarningMailEnabled = (bool) Setting::getValue(
      'notification.api_down_warning_mail.enabled',
      true
    );
    $this->welcomeEmailEnabled = (bool) Setting::getValue(
      'notification.welcome_email.enabled',
      true
    );
    $this->globalNotificationsEnabled = (bool) Setting::getValue(
      'notifications.global_enabled',
      true
    );
    $this->dataRetentionEnabled = (bool) Setting::getValue(
      'data_retention.enabled',
      false
    );
    $this->dataRetentionGlobalPeriod = (int) Setting::getValue(
      'data_retention.global_period', // New key for global period
      0
    );
    $this->telescopePruneFrequency = Setting::getValue(
      'notification.telescope_prune.value', // Key from data migration
      'weekly'
    );
    $this->adminPromotionEmailEnabled = (bool) Setting::getValue(
      'notification.admin_promotion.enabled', // New key
      true
    );

    // Ensure period is 0 if retention is disabled
    if (!$this->dataRetentionEnabled) {
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
      0 => 'Disabled', // Added option for disabled
      30 => '30 days',
      90 => '3 months',
      180 => '6 months',
      365 => '1 year',
      730 => '2 years',
      1095 => '3 years',
      1825 => '5 years',
    ];
  }

  /**
   * Get the available frequency options for the Telescope prune job.
   *
   * @return array<string, string> Key-value pairs of frequency slugs and labels.
   */
  public function getTelescopeFrequencyOptions(): array
  {
    return [
      'daily' => 'Daily',
      'weekly' => 'Weekly',
      'monthly' => 'Monthly',
    ];
  }

  // --- Updated Lifecycle Hooks for Immediate Saving --- //

  /**
   * Save sync frequency when the property is updated.
   * Livewire hook: https://livewire.laravel.com/docs/lifecycle-hooks#updated
   *
   * @param string $value The new value for syncFrequency.
   */
  public function updatedSyncFrequency($value): void
  {
    $this->saveSetting(
      'job_frequency.sync',
      $value,
      'Synchronization frequency updated.'
    );
  }

  /**
   * Save global notification enabled state when the property is updated.
   *
   * @param bool $value The new value for globalNotificationsEnabled.
   */
  public function updatedGlobalNotificationsEnabled($value): void
  {
    $this->saveSetting(
      'notifications.global_enabled',
      $value ? '1' : '0',
      'Global notification setting updated.'
    );
  }

  /**
   * Save API down warning email enabled state when the property is updated.
   *
   * @param bool $value The new value for apiDownWarningMailEnabled.
   */
  public function updatedApiDownWarningMailEnabled($value): void
  {
    $this->saveSetting(
      'notification.api_down_warning_mail.enabled',
      $value ? '1' : '0',
      'API down warning email setting updated.'
    );
  }

  /**
   * Save welcome email enabled state when the property is updated.
   *
   * @param bool $value The new value for welcomeEmailEnabled.
   */
  public function updatedWelcomeEmailEnabled($value): void
  {
    $this->saveSetting(
      'notification.welcome_email.enabled',
      $value ? '1' : '0',
      'Welcome email setting updated.'
    );
  }

  /**
   * Save data retention period and enabled state when the property is updated.
   *
   * @param int $value The new value for dataRetentionGlobalPeriod (in days).
   */
  public function updatedDataRetentionGlobalPeriod($value): void
  {
    $period = (int) $value;
    $isEnabled = $period > 0;

    try {
      // Save both the enabled state and the period value.
      Setting::setValue('data_retention.enabled', $isEnabled ? '1' : '0');
      Setting::setValue('data_retention.global_period', $period);

      // Update internal component state for the runDataRetention button.
      $this->dataRetentionEnabled = $isEnabled;

      $this->dispatch(
        'add-toast',
        message: 'Data retention settings updated.',
        variant: 'success'
      );
    } catch (Exception $e) {
      $this->dispatch(
        'add-toast',
        message: 'Failed to update data retention settings: ' .
          $e->getMessage(),
        variant: 'error'
      );
      // Revert component state from database if save fails.
      $this->dataRetentionGlobalPeriod = (int) Setting::getValue(
        'data_retention.global_period',
        0
      );
      $this->dataRetentionEnabled = (bool) Setting::getValue(
        'data_retention.enabled',
        false
      );
    }
  }

  /**
   * Save Telescope prune frequency when the property is updated.
   *
   * @param string $value The new value for telescopePruneFrequency.
   */
  public function updatedTelescopePruneFrequency($value): void
  {
    // Basic validation
    if (!array_key_exists($value, $this->getTelescopeFrequencyOptions())) {
      $this->dispatch(
        'add-toast',
        message: 'Invalid Telescope prune frequency selected.',
        variant: 'error'
      );
      // Revert component property to the old value from the database.
      $this->telescopePruneFrequency = Setting::getValue(
        'notification.telescope_prune.value',
        'weekly'
      );
      return;
    }
    $this->saveSetting(
      'notification.telescope_prune.value',
      $value,
      'Telescope prune frequency updated.'
    );
  }

  /**
   * Save admin promotion email enabled state when the property is updated.
   *
   * @param bool $value The new value for adminPromotionEmailEnabled.
   */
  public function updatedAdminPromotionEmailEnabled($value): void
  {
    $this->saveSetting(
      'notification.admin_promotion.enabled',
      $value ? '1' : '0',
      'Admin promotion email setting updated.'
    );
  }

  /**
   * Helper method to save a single setting value and dispatch feedback.
   *
   * @param string $key The database setting key.
   * @param mixed $value The value to save.
   * @param string $successMessage The message for the success toast.
   */
  private function saveSetting(
    string $key,
    $value,
    string $successMessage
  ): void {
    try {
      Setting::setValue($key, $value);
      $this->dispatch(
        'add-toast',
        message: $successMessage,
        variant: 'success'
      );
    } catch (Exception $e) {
      $this->dispatch(
        'add-toast',
        message: 'Failed to update setting: ' . $e->getMessage(),
        variant: 'error'
      );
      // Revert the associated component property by reloading from the database.
      $propertyName = $this->getPropertyNameFromSettingKey($key);
      if ($propertyName) {
        $this->{$propertyName} = Setting::getValue(
          $key,
          $this->getDefaultValueForKey($key)
        );
      }
    }
  }

  /**
   * Helper to get the component property name mapped from a setting key.
   * Used for reverting component state on save error.
   *
   * @param string $key The database setting key.
   * @return string|null The corresponding public property name or null if not mapped.
   */
  private function getPropertyNameFromSettingKey(string $key): ?string
  {
    $map = [
      'job_frequency.sync' => 'syncFrequency',
      'notifications.global_enabled' => 'globalNotificationsEnabled',
      'notification.api_down_warning_mail.enabled' =>
        'apiDownWarningMailEnabled',
      'notification.welcome_email.enabled' => 'welcomeEmailEnabled',
      'data_retention.global_period' => 'dataRetentionGlobalPeriod',
      'notification.telescope_prune.value' => 'telescopePruneFrequency',
      'notification.admin_promotion.enabled' => 'adminPromotionEmailEnabled', // Added mapping
      // 'data_retention.enabled' is handled within updatedDataRetentionGlobalPeriod
    ];
    return $map[$key] ?? null;
  }

  /**
   * Helper to get default values for reverting on error.
   */
  private function getDefaultValueForKey(string $key)
  {
    $defaults = [
      'job_frequency.sync' => 'everyThirtyMinutes',
      'notifications.global_enabled' => true,
      'notification.api_down_warning_mail.enabled' => true,
      'notification.welcome_email.enabled' => true,
      'data_retention.global_period' => 0,
      'notification.telescope_prune.value' => 'weekly',
      'notification.admin_promotion.enabled' => true, // Added default
    ];
    // Need to handle boolean conversion for string '1'/'0'
    $value = $defaults[$key] ?? null;
    if (
      in_array($key, [
        'notifications.global_enabled',
        'notification.api_down_warning_mail.enabled',
        'notification.welcome_email.enabled',
        'notification.admin_promotion.enabled', // Added key
      ])
    ) {
      return (bool) $value;
    }
    if ($key === 'data_retention.global_period') {
      return (int) $value;
    }
    return $value;
  }

  // --- Ping methods --- //

  /**
   * Ping the Odoo API endpoint to check connectivity.
   */
  public function pingOdoo(): void
  {
    $service = app(OdooApiCalls::class);
    $result = $service->ping();

    $this->dispatch(
      'add-toast',
      message: $result['message'],
      variant: $result['success'] ? 'success' : 'error'
    );
  }

  /**
   * Ping the Desktime API endpoint to check connectivity.
   */
  public function pingDesktime(): void
  {
    $service = app(DesktimeApiCalls::class);
    $success = $service->ping();

    $this->dispatch(
      'add-toast',
      message: $success
        ? 'DeskTime API connection successful'
        : 'DeskTime API connection failed',
      variant: $success ? 'success' : 'error'
    );
  }

  /**
   * Ping the Proofhub API endpoint to check connectivity.
   */
  public function pingProofhub(): void
  {
    $service = app(ProofhubApiCalls::class);
    $result = $service->ping();

    $this->dispatch(
      'add-toast',
      message: $result['message'],
      variant: $result['success'] ? 'success' : 'error'
    );
  }

  /**
   * Ping the SystemPin API endpoint to check connectivity.
   */
  public function pingSystemPin(): void
  {
    $service = app(SystemPinApiCalls::class);
    $success = $service->ping();

    $this->dispatch(
      'add-toast',
      message: $success
        ? 'SystemPin API connection successful'
        : 'SystemPin API connection failed',
      variant: $success ? 'success' : 'error'
    );
  }

  // --- Manual Action Methods --- //

  /**
   * Manually trigger the data retention artisan command.
   * Checks if data retention is enabled before running.
   */
  public function runDataRetention(): void
  {
    try {
      // Check the internal property which is updated by the hook
      if (
        !$this->dataRetentionEnabled ||
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
          message: 'Data retention process failed with exit code ' . $exitCode,
          variant: 'error'
        );
      }
    } catch (Exception $e) {
      $this->dispatch(
        'add-toast',
        message: 'Failed to run data retention: ' . $e->getMessage(),
        variant: 'error'
      );
    }
  }

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
    $activeUsers = User::whereHas('notificationPreferences', function ($query) {
      $query->where('mute_all', false);
    })->count();

    // Count active admins whose preferences are not muted
    $activeAdmins = User::where('is_admin', true)
      ->whereHas('notificationPreferences', function ($query) {
        $query->where('mute_all', false);
      })
      ->count();

    // Check if Telescope service is bound in the container (indicates installation)
    // and if the Telescope recording feature is enabled.
    $telescopeEnabled =
      app()->bound(EntriesRepository::class) && config('telescope.enabled');

    return view('livewire.settings', [
      'syncFrequencyOptions' => $this->getSyncFrequencyOptions(),
      'dataRetentionOptions' => $this->getDataRetentionOptions(),
      'telescopeFrequencyOptions' => $this->getTelescopeFrequencyOptions(),
      'totalUsers' => $totalUsers,
      'activeUsers' => $activeUsers,
      'activeAdmins' => $activeAdmins,
      'telescopeEnabled' => $telescopeEnabled,
    ]);
  }
}
