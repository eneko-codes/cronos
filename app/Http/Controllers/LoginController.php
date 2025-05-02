<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LoginToken;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LoginController extends Controller
{
    /**
     * Verify the login token and authenticate the user.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function verify(Request $request)
    {
        $ipAddress = $request->ip();
        $userAgent = $request->header('User-Agent');
        $email = ''; // Will be populated if we can find the token
        $timestamp = Carbon::now()->toIso8601String();

        // Log the initial verification attempt
        Log::info('Login token verification attempt', [
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'token_present' => $request->has('token'),
            'timestamp' => $timestamp,
        ]);

        // Validate the token input
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|size:60',
            'remember' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            // Log validation failure
            Log::info('Token validation failed - invalid format', [
                'email' => $email,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'timestamp' => $timestamp,
            ]);

            return redirect()->route('login')->withErrors($validator->errors());
        }

        // Retrieve the token and remember flag from the request query parameters
        $token = $request->query('token');
        $remember = $request->query('remember') === '1';

        // Hash the token and search for it in the database
        $hashedToken = hash('sha256', $token);
        $loginToken = LoginToken::where('token', $hashedToken)->first();

        // Check if the token exists and is valid
        if ($loginToken) {
            $email = $loginToken->user->email;
        }

        if (! $loginToken) {
            // Log token not found
            Log::info('Token verification failed - token not found', [
                'email' => $email,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'token_prefix' => substr($token, 0, 8).'...', // Only log prefix for security
                'timestamp' => $timestamp,
            ]);

            return redirect()
                ->route('login')
                ->withErrors(['token' => 'Token not found or already used.']);
        }

        // Check if the token has expired
        if (Carbon::now()->greaterThan($loginToken->expires_at)) {
            // Log expired token
            Log::info('Token verification failed - token expired', [
                'email' => $email,
                'name' => $loginToken->user->name,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'token_expires_at' => $loginToken->expires_at->toIso8601String(),
                'timestamp' => $timestamp,
            ]);

            $loginToken->delete();

            return redirect()
                ->route('login')
                ->withErrors(['token' => 'The token has expired.']);
        }

        // Authenticate the user and delete the token within a transaction
        DB::transaction(function () use ($loginToken, $remember, $request) {
            Auth::login($loginToken->user, $remember); // Pass the $remember flag

            // Optionally, set a session variable for last activity if needed
            $request->session()->put('last_activity', Carbon::now()->timestamp);

            // Delete the token after it has been used
            $loginToken->delete();
        });

        // Log the successful authentication
        Log::info('User authenticated successfully: '.$loginToken->user->name, [
            'email' => $email,
            'name' => $loginToken->user->name,
            'user_id' => $loginToken->user_id,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'session_id' => $request->session()->getId(),
            'remember_me' => $remember,
            'timestamp' => $timestamp,
        ]);

        // Redirect to the dashboard with a success message
        return redirect()
            ->route('dashboard')
            ->with('success', 'You have successfully logged in.');
    }

    /**
     * Handle the logout request.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout(Request $request)
    {
        $ipAddress = $request->ip();
        $userAgent = $request->header('User-Agent');
        $email = Auth::user() ? Auth::user()->email : '';
        $name = Auth::user() ? Auth::user()->name : 'Unknown user';
        $userId = Auth::id();
        $timestamp = Carbon::now()->toIso8601String();
        $sessionId = session()->getId();

        // Log out the user
        Auth::logout();

        // Log the logout
        Log::info('User logged out: '.$name, [
            'email' => $email,
            'name' => $name,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'session_id' => $sessionId,
            'timestamp' => $timestamp,
        ]);

        // Invalidate the session and regenerate the CSRF token
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Redirect to the login page with a success message
        return redirect()
            ->route('login')
            ->with('success', 'You have been logged out successfully.');
    }
}
