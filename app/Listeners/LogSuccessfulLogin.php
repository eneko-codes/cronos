<?php

namespace App\Listeners;

use Carbon\Carbon;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

/**
 * Listener to log successful logins
 */
class LogSuccessfulLogin
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
  public function handle(Login $event): void
  {
    $ipAddress = Request::ip();
    $userAgent = Request::header('User-Agent');
    $timestamp = Carbon::now()->toIso8601String();
    $sessionId = session()->getId();

    // Get user information
    $user = $event->user;

    // Prepare log data
    $logData = [
      'user_id' => $user->id,
      'user_email' => $user->email,
      'is_admin' => $user->is_admin ?? false,
      'session_id' => $sessionId,
      'ip_address' => $ipAddress,
      'user_agent' => $userAgent,
      'timestamp' => $timestamp,
      'guard' => $event->guard,
    ];

    // Log the successful login
    Log::channel('auth')->info('Standard login successful', $logData);
  }
}
