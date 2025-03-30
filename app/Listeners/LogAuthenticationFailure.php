<?php

namespace App\Listeners;

use Carbon\Carbon;
use Illuminate\Auth\Events\Failed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

/**
 * Listener to log authentication failures
 */
class LogAuthenticationFailure
{
  /**
   * Create the event listener.
   */
  public function __construct()
  {
    //
  }

  /**
   * Handle the event.
   */
  public function handle(Failed $event): void
  {
    $ipAddress = Request::ip();
    $userAgent = Request::header('User-Agent');
    $timestamp = Carbon::now()->toIso8601String();

    // Get user information if it exists
    $userId = $event->user?->id ?? null;
    $userEmail = $event->user?->email ?? ($event->credentials['email'] ?? null);

    // Prepare log data
    $logData = [
      'ip_address' => $ipAddress,
      'user_agent' => $userAgent,
      'timestamp' => $timestamp,
      'guard' => $event->guard,
    ];

    // Add user information if available
    if ($userId) {
      $logData['user_id'] = $userId;
    }

    if ($userEmail) {
      $logData['email'] = $userEmail;
    }

    // Log the failed authentication attempt
    Log::channel('auth')->warning('Authentication failed', $logData);
  }
}
