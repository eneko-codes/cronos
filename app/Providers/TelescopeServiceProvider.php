<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
  /**
   * Register any application services.
   */
  public function register(): void
  {
    Telescope::night();

    $this->hideSensitiveRequestDetails();

    $isLocal = $this->app->environment('local');

    Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
      // Allow all entries for debugging even if not local (so Telescope is always available even in production servers)
      return true;

      // Original logic:
      // return $isLocal ||
      //   $entry->isReportableException() ||
      //   $entry->isFailedRequest() ||
      //   $entry->isFailedJob() ||
      //   $entry->isScheduledTask() ||
      //   $entry->hasMonitoredTag() ||
      //   $entry->isLog();
    });
  }

  /**
   * Prevent sensitive request details from being logged by Telescope.
   */
  protected function hideSensitiveRequestDetails(): void
  {
    if ($this->app->environment('local')) {
      return;
    }

    Telescope::hideRequestParameters(['_token']);

    Telescope::hideRequestHeaders(['cookie', 'x-csrf-token', 'x-xsrf-token']);
  }

  /**
   * Register the Telescope gate.
   *
   * This gate determines who can access Telescope in non-local environments.
   */
  protected function gate(): void
  {
    // Only users who are admin can access telescope in production
    Gate::define('viewTelescope', function ($user) {
      return $user->is_admin;
    });
  }
}
