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
    // Validate the token input
    $validator = Validator::make($request->all(), [
      'token' => 'required|string|size:60',
      'remember' => 'nullable|boolean',
    ]);

    if ($validator->fails()) {
      return redirect()
        ->route('login')
        ->withErrors($validator->errors());
    }

    // Retrieve the token and remember flag from the request query parameters
    $token = $request->query('token');
    $remember = $request->query('remember') === '1';

    // Hash the token and search for it in the database
    $hashedToken = hash('sha256', $token);
    $loginToken = LoginToken::where('token', $hashedToken)->first();

    // Check if the token exists and is valid
    if (!$loginToken) {
      Log::warning('Login token not found or already used.', [
        'token' => $token,
        'ip' => $request->ip(),
      ]);

      return redirect()
        ->route('login')
        ->withErrors(['token' => 'Token not found or already used.']);
    }

    // Check if the token has expired
    if (Carbon::now()->greaterThan($loginToken->expires_at)) {
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
