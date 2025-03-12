<?php

namespace App\Providers;

use App\Events\TimezoneUpdated;
use App\Listeners\SendWelcomeEmail;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Event Service Provider
 *
 * Handles all event-listener mappings for the application:
 * - UserCreated: Triggers welcome email
 * - TimezoneUpdated: Handles timezone changes
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
