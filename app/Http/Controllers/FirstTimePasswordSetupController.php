<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\FirstTimePasswordSetupRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * FirstTimePasswordSetupController
 *
 * Handles first-time password setup for new users who don't have a password yet.
 * This is used when users are created without passwords (e.g., imported from external systems)
 * and need to set up their initial password via a secure token link.
 *
 * Security Features:
 * - Token validation using Laravel's Password broker (validates hash and expiration)
 * - Ensures user doesn't already have a password set
 * - Email validation to prevent manipulation
 * - Rate limiting via throttle middleware to prevent abuse
 * - Automatic login after successful password setup with session regeneration
 */
class FirstTimePasswordSetupController extends Controller
{
    /**
     * Display the first-time password setup form.
     *
     * Validates the setup token and ensures the user exists and doesn't have a password
     * set. Uses Laravel's native Password broker to validate the token, which checks
     * both the token hash and expiration automatically.
     *
     * @param  \Illuminate\Http\Request  $request  The HTTP request containing token and email from URL
     * @return \Illuminate\View\View The password setup form view with token validation status
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 403 if token or email is missing
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException 404 if user not found or already has password
     */
    public function create(Request $request): View
    {
        $email = $request->get('email');
        $token = $request->route('token') ?? $request->get('token');

        // Validate token for security
        if (! $token) {
            abort(403, 'Invalid setup link. Please use the link from your welcome email.');
        }

        // Validate email is provided
        if (! $email) {
            abort(403, 'Email is required. Please use the link from your welcome email.');
        }

        // Find user and ensure they don't have a password set
        $user = User::where('email', $email)->whereNull('password')->first();

        if (! $user) {
            abort(404, 'User not found or already has a password set.');
        }

        // Use Laravel's native Password broker to validate token
        // This validates both token hash and expiration automatically
        // Also ensures the email matches the token
        $tokenValid = Password::tokenExists($user, $token);

        return view('auth.first-time-password-setup', [
            'email' => $email,
            'token' => $token,
            'tokenValid' => $tokenValid,
        ]);
    }

    /**
     * Handle the first-time password setup request.
     *
     * Sets the user's password using Laravel's native Password broker. The broker
     * automatically handles token validation, expiration checking, and user lookup.
     *
     * After successfully setting the password:
     * - Logs the password setup event for auditing
     * - Automatically logs the user in
     * - Regenerates the session to prevent session fixation attacks
     * - Redirects to the dashboard
     *
     * The password is hashed using Laravel's Hash facade following Laravel 12
     * best practices.
     *
     * @param  \App\Http\Requests\FirstTimePasswordSetupRequest  $request  The validated password setup request containing email, token, and new password
     * @return \Illuminate\Http\RedirectResponse Redirects to dashboard on success, back with errors on failure
     *
     * @throws \Illuminate\Validation\ValidationException If validation fails
     */
    public function store(FirstTimePasswordSetupRequest $request): RedirectResponse
    {
        $validatedData = $request->validated();

        // Use Laravel's native Password broker for secure token validation
        // The broker handles token validation, expiration, and user lookup automatically
        $status = Password::reset(
            $validatedData,
            function ($user, $password): void {
                // Set the user's password using Laravel's Hash facade (Laravel 12 best practice)
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                // Log the password setup event
                Log::info('User set up password for first time', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->header('User-Agent'),
                ]);

                // Automatically log the user in after password setup
                Auth::login($user);

                // Regenerate session after login to prevent session fixation attacks
                request()->session()->regenerate();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('dashboard')->with('toast', [
                'message' => 'Password set successfully! You are now logged in.',
                'variant' => 'success',
            ]);
        }

        // Handle throttled requests specifically
        if ($status === Password::RESET_THROTTLED) {
            return back()->withErrors(['rate_limit' => __($status)]);
        }

        return back()->withErrors(['email' => [__($status)]]);
    }
}
