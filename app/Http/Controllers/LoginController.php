<?php

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
   * @param Request $request
   * @return \Illuminate\Http\RedirectResponse
   */
  public function verify(Request $request)
  {
    $ipAddress = $request->ip();
    $userAgent = $request->header('User-Agent');
    $timestamp = Carbon::now()->toIso8601String();

    // Log the verification attempt
    Log::channel('auth')->info('Login token verification attempt', [
      'ip_address' => $ipAddress,
      'user_agent' => $userAgent,
      'timestamp' => $timestamp,
      'token_present' => $request->has('token'),
    ]);

    // Validate the token input
    $validator = Validator::make($request->all(), [
      'token' => 'required|string|size:60',
      'remember' => 'nullable|boolean',
    ]);

    if ($validator->fails()) {
      Log::channel('auth')->warning('Login token validation failed', [
        'ip_address' => $ipAddress,
        'errors' => $validator->errors()->toArray(),
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
    if (!$loginToken) {
      Log::channel('auth')->warning('Login token not found or already used', [
        'token' => substr($token, 0, 8) . '...', // Only log prefix of token for security
        'ip_address' => $ipAddress,
        'user_agent' => $userAgent,
        'timestamp' => $timestamp,
      ]);

      return redirect()
        ->route('login')
        ->withErrors(['token' => 'Token not found or already used.']);
    }

    // Check if the token has expired
    if (Carbon::now()->greaterThan($loginToken->expires_at)) {
      Log::channel('auth')->warning('Login token expired', [
        'token_id' => $loginToken->id,
        'user_id' => $loginToken->user_id,
        'ip_address' => $ipAddress,
        'user_agent' => $userAgent,
        'token_expired_at' => $loginToken->expires_at->toIso8601String(),
        'timestamp' => $timestamp,
      ]);

      $loginToken->delete();

      return redirect()
        ->route('login')
        ->withErrors(['token' => 'The token has expired.']);
    }

    // Log the successful token validation before authentication
    Log::channel('auth')->info('Valid login token found', [
      'token_id' => $loginToken->id,
      'user_id' => $loginToken->user_id,
      'user_email' => $loginToken->user->email,
      'user_name' => $loginToken->user->name,
      'remember_me' => $remember,
      'ip_address' => $ipAddress,
      'user_agent' => $userAgent,
      'timestamp' => $timestamp,
    ]);

    // Authenticate the user and delete the token within a transaction
    DB::transaction(function () use (
      $loginToken,
      $remember,
      $request,
      $ipAddress,
      $userAgent,
      $timestamp
    ) {
      Auth::login($loginToken->user, $remember); // Pass the $remember flag

      // Optionally, set a session variable for last activity if needed
      $request->session()->put('last_activity', Carbon::now()->timestamp);

      // Delete the token after it has been used
      $loginToken->delete();

      // Log the successful login
      Log::channel('auth')->info('User successfully authenticated', [
        'user_id' => $loginToken->user_id,
        'user_email' => $loginToken->user->email,
        'is_admin' => $loginToken->user->is_admin,
        'session_id' => $request->session()->getId(),
        'remember_me' => $remember,
        'ip_address' => $ipAddress,
        'user_agent' => $userAgent,
        'timestamp' => $timestamp,
      ]);
    });

    // Redirect to the dashboard with a success message
    return redirect()
      ->route('dashboard')
      ->with('success', 'You have successfully logged in.');
  }

  /**
   * Handle the logout request.
   *
   * @param Request $request
   * @return \Illuminate\Http\RedirectResponse
   */
  public function logout(Request $request)
  {
    // Log out the user
    Auth::logout();

    // Invalidate the session and regenerate the CSRF token
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    // Redirect to the login page with a success message
    return redirect()
      ->route('login')
      ->with('success', 'You have been logged out successfully.');
  }
}
