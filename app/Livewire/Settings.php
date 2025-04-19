<?php

namespace App\Livewire;

use App\Models\Setting;
use App\Models\User;
use App\Services\DesktimeApiCalls;
use App\Services\OdooApiCalls;
use App\Services\ProofhubApiCalls;
use Exception;
use Illuminate\Support\Facades\Log;
use Laravel\Telescope\Contracts\EntriesRepository;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Facades\Artisan;

#[Title('Settings')]
class Settings extends Component
{
  // Settings properties - using keys from the new Setting model
  public string $syncFrequency = 'everyThirtyMinutes';
  public bool $apiDownWarningMailEnabled = false;
  public bool $welcomeEmailEnabled = false;
  public bool $dataRetentionEnabled = false;
  public int $dataRetentionGlobalPeriod = 0; // Stores the global retention period in days
  public string $telescopePruneFrequency = 'weekly';

  public function mount(): void
  {
    // Load settings from the new Setting model
    $this->syncFrequency = Setting::getValue(
      'job_frequency.sync',
      'everyThirtyMinutes'
    );
    $this->apiDownWarningMailEnabled = (bool) Setting::getValue(
      'notification.api_down_warning_mail.enabled',
      false
    );
    $this->welcomeEmailEnabled = (bool) Setting::getValue(
      'notification.welcome_email.enabled',
      false
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

    // Ensure period is 0 if retention is disabled
    if (!$this->dataRetentionEnabled) {
      $this->dataRetentionGlobalPeriod = 0;
    }
  }

  // Method to get frequency options for the sync job
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

  // Method to get retention options
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

  // Method to get Telescope prune options
  public function getTelescopeFrequencyOptions(): array
  {
    return [
      'daily' => 'Daily',
      'weekly' => 'Weekly',
      'monthly' => 'Monthly',
    ];
  }

  public function updateSyncFrequency(): void
  {
    try {
      Setting::setValue('job_frequency.sync', $this->syncFrequency);
      $this->dispatch(
        'add-toast',
        message: 'Synchronization frequency updated.',
        variant: 'success'
      );
    } catch (Exception $e) {
      Log::error('Failed to update sync frequency setting', [
        'error' => $e->getMessage(),
      ]);
      $this->dispatch(
        'add-toast',
        message: 'Failed to update frequency: ' . $e->getMessage(),
        variant: 'error'
      );
    }
  }

  public function updateNotificationToggles(): void
  {
    try {
      Setting::setValue(
        'notification.api_down_warning_mail.enabled',
        $this->apiDownWarningMailEnabled ? '1' : '0' // Store bool as '1'/'0'
      );
      Setting::setValue(
        'notification.welcome_email.enabled',
        $this->welcomeEmailEnabled ? '1' : '0' // Store bool as '1'/'0'
      );

      $this->dispatch(
        'add-toast',
        message: 'Notification settings updated.',
        variant: 'success'
      );
    } catch (Exception $e) {
      Log::error('Failed to update notification toggle settings', [
        'error' => $e->getMessage(),
      ]);
      $this->dispatch(
        'add-toast',
        message: 'Failed to update toggles: ' . $e->getMessage(),
        variant: 'error'
      );
    }
  }

  public function updateDataRetentionSettings(): void
  {
    try {
      // If period is 0, disable retention; otherwise, enable it.
      $isEnabled = $this->dataRetentionGlobalPeriod > 0;

      Setting::setValue('data_retention.enabled', $isEnabled ? '1' : '0');
      Setting::setValue(
        'data_retention.global_period',
        (int) $this->dataRetentionGlobalPeriod
      );

      // Update internal state
      $this->dataRetentionEnabled = $isEnabled;

      $this->dispatch(
        'add-toast',
        message: 'Data retention settings updated.',
        variant: 'success'
      );
    } catch (Exception $e) {
      Log::error('Failed to update data retention settings', [
        'error' => $e->getMessage(),
      ]);
      $this->dispatch(
        'add-toast',
        message: 'Failed to update data retention settings: ' .
          $e->getMessage(),
        variant: 'error'
      );
    }
  }

  public function updateTelescopePruneSettings(): void
  {
    // Basic validation
    if (
      !array_key_exists(
        $this->telescopePruneFrequency,
        $this->getTelescopeFrequencyOptions()
      )
    ) {
      $this->dispatch(
        'add-toast',
        message: 'Invalid Telescope prune frequency selected.',
        variant: 'error'
      );
      // Optionally reset to a default or previous value
      $this->telescopePruneFrequency = Setting::getValue(
        'notification.telescope_prune.value',
        'weekly'
      );
      return;
    }

    try {
      Setting::setValue(
        'notification.telescope_prune.value',
        $this->telescopePruneFrequency
      );
      $this->dispatch(
        'add-toast',
        message: 'Telescope prune frequency updated.',
        variant: 'success'
      );
    } catch (Exception $e) {
      Log::error('Failed to update Telescope prune setting', [
        'error' => $e->getMessage(),
      ]);
      $this->dispatch(
        'add-toast',
        message: 'Failed to update Telescope prune frequency: ' .
          $e->getMessage(),
        variant: 'error'
      );
    }
  }

  // --- Ping methods remain the same --- //

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

  // --- Manual purge methods remain similar, just check the new property --- //

  public function runDataRetention(): void
  {
    try {
      // Check the new enabled property
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

      // Execute the command (assuming it reads from settings or is updated separately)
      // IMPORTANT: The Artisan command 'app:purge-old-time-data' might need updating
      // to use the new Setting model as well.
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
      Log::error('Failed to run manual data retention', [
        'error' => $e->getMessage(),
      ]);
      $this->dispatch(
        'add-toast',
        message: 'Failed to run data retention: ' . $e->getMessage(),
        variant: 'error'
      );
    }
  }

  public function runTelescopePrune(): void
  {
    try {
      $exitCode = Artisan::call('telescope:prune');

      if ($exitCode === 0) {
        $this->dispatch(
          'add-toast',
          message: 'Telescope pruning completed successfully.',
          variant: 'success'
        );
      } else {
        $this->dispatch(
          'add-toast',
          message: 'Telescope pruning failed with exit code ' . $exitCode,
          variant: 'error'
        );
      }
    } catch (Exception $e) {
      Log::error('Failed to run manual telescope prune', [
        'error' => $e->getMessage(),
      ]);
      $this->dispatch(
        'add-toast',
        message: 'Failed to run Telescope pruning: ' . $e->getMessage(),
        variant: 'error'
      );
    }
  }

  public function render()
  {
    $totalUsers = User::count();
    $activeUsers = User::where('muted_notifications', false)->count();
    $activeAdmins = User::where('is_admin', true)
      ->where('muted_notifications', false)
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
