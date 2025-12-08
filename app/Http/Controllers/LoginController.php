<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Notifications\WelcomeNewUserEmail;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * LoginController
 *
 * Handles user authentication including login and logout functionality.
 * Uses Laravel 12 native authentication features with proper session management
 * and security best practices.
 *
 * Security Features:
 * - Session regeneration after successful login to prevent session fixation
 * - Rate limiting via throttle middleware to prevent brute-force attacks
 * - User enumeration protection by showing consistent error messages
 * - Automatic welcome email resending for users without passwords
 */
class LoginController extends Controller
{
    /**
     * Display the login view.
     *
     * Shows the login form to unauthenticated users. This route is protected
     * by the 'guest' middleware to ensure only unauthenticated users can access it.
     *
     * @return \Illuminate\View\View The login view
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming login request.
     *
     * Authenticates the user using Laravel's native Auth facade. On successful
     * authentication, regenerates the session to prevent session fixation attacks
     * and logs the authentication event for security auditing.
     *
     * If authentication fails, checks if the user exists but doesn't have a password
     * set. In such cases, silently sends a welcome email without revealing user
     * existence to prevent user enumeration attacks.
     *
     * @param  \App\Http\Requests\LoginRequest  $loginRequest  The validated login request containing email, password, and remember flag
     * @return \Illuminate\Http\RedirectResponse Redirects to dashboard on success, back with errors on failure
     *
     * @throws \Illuminate\Validation\ValidationException If validation fails
     */
    public function store(LoginRequest $loginRequest): RedirectResponse
    {
        $validatedLoginData = $loginRequest->validated();
        $email = $validatedLoginData['email'];
        $password = $validatedLoginData['password'];
        $remember = (bool) ($validatedLoginData['remember'] ?? false);

        $ipAddress = request()->ip();
        $userAgent = request()->header('User-Agent');

        // Attempt to authenticate the user using Laravel's native authentication
        // This uses users.email (primary authentication email synced from Odoo)
        // Laravel 12 requires the email field on the User model for Auth::attempt()
        // See User model documentation for details on the email architecture
        if (Auth::attempt(['email' => $email, 'password' => $password], $remember)) {
            $user = Auth::user();

            // Regenerate session AFTER successful authentication to prevent session fixation attacks
            request()->session()->regenerate();

            // Log the successful authentication event
            Log::info('User authenticated successfully: '.$user->name, [
                'user_id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'session_id' => request()->session()->getId(),
                'remember_me' => $remember,
            ]);

            return redirect()->intended(route('dashboard'))->with('toast', [
                'message' => "Welcome back {$user->name}!",
                'variant' => 'success',
            ]);
        }

        // If authentication failed, check if user exists but doesn't have a password set
        // Note: This check happens after auth failure to minimize user enumeration risk
        // We always show the same error message regardless of whether user exists
        // but silently send welcome email if user needs password setup
        //
        // This lookup uses users.email (primary authentication email) which is
        // synced from Odoo and always present for users created via sync jobs
        $user = User::where('email', $email)->whereNull('password')->first();
        if ($user) {
            // Resend welcome email instead of allowing direct access
            $user->notify(new WelcomeNewUserEmail);
        }

        return back()->withInput(['email' => $email, 'remember' => $remember])->withErrors([
            'credentials' => 'These credentials do not match our records.',
        ]);
    }

    /**
     * Log the user out of the application.
     *
     * Performs a secure logout by:
     * 1. Logging out the user via Auth facade
     * 2. Invalidating the current session
     * 3. Regenerating the CSRF token
     * 4. Logging the logout event for security auditing
     *
     * This route is protected by the 'auth' middleware to ensure only
     * authenticated users can logout.
     *
     * @param  \Illuminate\Http\Request  $httpRequest  The HTTP request instance
     * @return \Illuminate\Http\RedirectResponse Redirects to login page
     */
    public function logout(Request $httpRequest): RedirectResponse
    {
        $user = Auth::user();
        $ipAddress = $httpRequest->ip();
        $userAgent = $httpRequest->header('User-Agent');
        $sessionId = $httpRequest->session()->getId();

        Auth::logout();

        $httpRequest->session()->invalidate();
        $httpRequest->session()->regenerateToken();

        // Log the logout event with detailed information
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

        return redirect()->route('login');
    }
}
