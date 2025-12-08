<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * ResetPasswordController
 *
 * Handles password reset functionality for users who have forgotten their passwords.
 * Uses Laravel 12 native Password broker for secure token validation and password reset.
 *
 * Security Features:
 * - Token validation using Laravel's Password broker (validates hash and expiration)
 * - Email comes from URL parameter, not user input, preventing email manipulation
 * - Rate limiting via throttle middleware to prevent abuse
 * - Token expiration enforced by Laravel's password reset system
 */
class ResetPasswordController extends Controller
{
    /**
     * Display the password reset form.
     *
     * Validates the password reset token using Laravel's native Password broker.
     * The token and email are extracted from the URL parameters (from the reset link
     * sent via email), ensuring users cannot manipulate the email to reset someone
     * else's password.
     *
     * @param  \Illuminate\Http\Request  $request  The HTTP request containing token and email from URL
     * @return \Illuminate\View\View The password reset form view with token validation status
     */
    public function create(Request $request): View
    {
        $token = $request->route('token');
        $email = $request->get('email');

        // Use Laravel's native Password broker to validate token
        // This validates both token hash and expiration automatically
        $tokenValid = false;
        if ($email && $token) {
            $user = User::where('email', $email)->first();
            if ($user) {
                $tokenValid = Password::tokenExists($user, $token);
            }
        }

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $email,
            'tokenValid' => $tokenValid,
        ]);
    }

    /**
     * Handle an incoming new password request.
     *
     * Resets the user's password using Laravel's native Password broker. The broker
     * automatically handles:
     * - Token validation (hash verification)
     * - Token expiration checking
     * - User lookup by email
     * - Token deletion after successful reset
     *
     * The password is hashed using Laravel's Hash facade following Laravel 12
     * best practices.
     *
     * @param  \App\Http\Requests\ResetPasswordRequest  $request  The validated password reset request containing email, token, and new password
     * @return \Illuminate\Http\RedirectResponse Redirects to login page on success, back with errors on failure
     *
     * @throws \Illuminate\Validation\ValidationException If validation fails
     */
    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        $validatedData = $request->validated();

        // Attempt to reset the user's password using Laravel's native Password broker
        // The broker handles token validation, expiration, and user lookup automatically
        $status = Password::reset(
            $validatedData,
            function ($user, $password): void {
                // Set password and mark email as verified
                // The user has proven they own this email by clicking the reset link
                $user->forceFill([
                    'password' => Hash::make($password),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('toast', [
                'message' => __($status),
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
