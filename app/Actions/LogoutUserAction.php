<?php

namespace App\Actions\Auth;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Action class responsible for handling user logout.
 */
class LogoutUserAction
{
    /**
     * Logs the user out, invalidates their session, regenerates the CSRF token,
     * and logs the logout event.
     *
     * @param  Request  $request  The incoming HTTP request.
     */
    public function handle(Request $request): void
    {
        $ipAddress = $request->ip();
        $userAgent = $request->header('User-Agent');
        $user = Auth::user();
        $sessionId = $request->session()->getId();

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        // Log the logout event
        $email = $user ? $user->email : 'Unknown';
        $name = $user ? $user->name : 'Unknown user';
        $userId = $user ? $user->id : null;
        $timestamp = Carbon::now()->toIso8601String();

        Log::info('User logged out: '.$name, [
            'user_id' => $userId,
            'email' => $email,
            'name' => $name,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'session_id' => $sessionId,
            'timestamp' => $timestamp,
        ]);
    }
}
