<?php

namespace App\Providers;

use App\Events\TimezoneUpdated;
use App\Listeners\LogAuthenticationFailure;
use App\Listeners\LogSuccessfulLogin;
use App\Listeners\SendWelcomeEmail;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Event Service Provider
 *
 * Handles all event-listener mappings for the application:
 * - UserCreated: Triggers welcome email
 * - TimezoneUpdated: Handles timezone changes
 * - Failed: Logs authentication failures
 * - Login: Logs successful logins
 */
class EventServiceProvider extends ServiceProvider
{
  /**
   * The event listener mappings for the application.
   *
   * @var array
   */
  protected $listen = [
    TimezoneUpdated::class => [],

    // Authentication Events
    Failed::class => [LogAuthenticationFailure::class],
    Login::class => [LogSuccessfulLogin::class],
  ];

  /**
   * Register any events for your application.
   *
   * @return void
   */
  public function boot(): void
  {
    parent::boot();
  }
}
