<?php

namespace App\Livewire;

use App\Models\DataRetentionSetting;
use App\Models\JobFrequency;
use App\Models\NotificationSetting;
use App\Models\User;
use App\Services\DesktimeApiCalls;
use App\Services\OdooApiCalls;
use App\Services\ProofhubApiCalls;
use Exception;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Settings')]
class Settings extends Component
{
  public string $frequency;
  public bool $apiDownWarning;
  public bool $welcomeEmail;
  // New simplified data retention property
  public int $globalRetentionPeriod = 0;

  // Telescope Pruning Settings - Simplified
  public string $telescopePruneFrequency = 'weekly';

  public function mount(): void
  {
    // Get frequency from DB
    $this->frequency = JobFrequency::getConfig()->frequency;
    // Get toggles from DB
    $this->apiDownWarning = (bool) NotificationSetting::isEnabled(
      'api_down_warning_mail'
    );
    $this->welcomeEmail = (bool) NotificationSetting::isEnabled(
      'welcome_email'
    );

    // Load data retention settings
    $this->loadDataRetentionSettings();

    // Load Telescope prune settings
    $this->telescopePruneFrequency = NotificationSetting::getValue(
      'telescope_prune_frequency',
      'weekly' // Default frequency
    );
  }

  // Extracted data retention loading logic
  private function loadDataRetentionSettings(): void
  {
    $this->globalRetentionPeriod = 0; // Reset to default
    // Check if any data type has a retention setting
    foreach (DataRetentionSetting::dataTypes() as $dataType => $label) {
      $setting = DataRetentionSetting::forType($dataType);
      if ($setting && $setting->retention_days > 0) {
        // Use the first non-zero retention period found
        $this->globalRetentionPeriod = $setting->retention_days;
        break;
      }
    }

    // If data retention is disabled in notification settings, set period to 0
    if (!NotificationSetting::isEnabled('data_retention_enabled')) {
      $this->globalRetentionPeriod = 0;
    }
  }

  public function updateFrequencies(): void
  {
    try {
      JobFrequency::getConfig()->update(['frequency' => $this->frequency]);
      $this->dispatch(
        'add-toast',
        message: 'Synchronization frequency updated.',
        variant: 'success'
      );
    } catch (Exception $e) {
      $this->dispatch(
        'add-toast',
        message: 'Failed to update frequency: ' . $e->getMessage(),
        variant: 'error'
      );
    }
  }

  /**
   * Update all toggles in notification_settings table.
   */
  public function updateNotificationToggles(): void
  {
    try {
      NotificationSetting::updateOrCreate(
        ['key' => 'api_down_warning_mail'],
        ['enabled' => $this->apiDownWarning]
      );

      NotificationSetting::updateOrCreate(
        ['key' => 'welcome_email'],
        ['enabled' => $this->welcomeEmail]
      );

      $this->dispatch(
        'add-toast',
        message: 'Notification settings updated.',
        variant: 'success'
      );
    } catch (Exception $e) {
      $this->dispatch(
        'add-toast',
        message: 'Failed to update toggles: ' . $e->getMessage(),
        variant: 'error'
      );
    }
  }

  /**
   * Update data retention settings.
   */
  public function updateDataRetentionSettings(): void
  {
    try {
      // Update the main toggle - if globalRetentionPeriod is 0, disable data retention
      $isEnabled = $this->globalRetentionPeriod > 0;
      NotificationSetting::updateOrCreate(
        ['key' => 'data_retention_enabled'],
        ['enabled' => $isEnabled]
      );

      // If enabled, update retention periods for all data types to the same value
      if ($isEnabled) {
        foreach (DataRetentionSetting::dataTypes() as $dataType => $label) {
          DataRetentionSetting::updateOrCreate(
            ['data_type' => $dataType],
            ['retention_days' => (int) $this->globalRetentionPeriod]
          );
        }
      }

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
    }
  }

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

  /**
   * Run the data retention purge command manually
   */
  public function runDataRetention(): void
  {
    try {
      // Only run if data retention is enabled
      if ($this->globalRetentionPeriod <= 0) {
        $this->dispatch(
          'add-toast',
          message: 'Data retention is disabled. Please enable it first.',
          variant: 'error'
        );
        return;
      }

      // Execute the command
      $exitCode = \Illuminate\Support\Facades\Artisan::call(
        'app:purge-old-time-data'
      );

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
   * Update Telescope pruning settings.
   */
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
        message: 'Invalid frequency selected.',
        variant: 'error'
      );
      return;
    }

    try {
      NotificationSetting::updateOrCreate(
        ['key' => 'telescope_prune_frequency'],
        ['value' => $this->telescopePruneFrequency]
      );

      $this->dispatch(
        'add-toast',
        message: 'Telescope prune settings updated.',
        variant: 'success'
      );
    } catch (Exception $e) {
      // Log the exception so it appears in Telescope Logs
      Log::error(
        'Failed to update Telescope prune settings: ' . $e->getMessage(),
        [
          'exception' => $e,
        ]
      );

      $this->dispatch(
        'add-toast',
        message: 'Failed to update Telescope prune settings: ' .
          $e->getMessage(),
        variant: 'error'
      );
    }
  }

  /**
   * Get options for Telescope prune frequency select dropdown.
   */
  public function getTelescopeFrequencyOptions(): array
  {
    // Removed 'never' option
    return [
      'daily' => 'Daily',
      'weekly' => 'Weekly',
      'monthly' => 'Monthly',
    ];
  }

  public function render()
  {
    $totalUsers = User::count();
    $activeUsers = User::where('muted_notifications', false)->count();
    $activeAdmins = User::where('is_admin', true)
      ->where('muted_notifications', false)
      ->count();

    return view('livewire.settings', [
      'options' => JobFrequency::getFrequencyOptions(),
      'totalUsers' => $totalUsers,
      'activeUsers' => $activeUsers,
      'activeAdmins' => $activeAdmins,
      'retentionOptions' => DataRetentionSetting::retentionOptions(),
      'dataTypes' => DataRetentionSetting::dataTypes(),
      // Pass only frequency options to the view
      'telescopeFrequencyOptions' => $this->getTelescopeFrequencyOptions(),
    ]);
  }
}
