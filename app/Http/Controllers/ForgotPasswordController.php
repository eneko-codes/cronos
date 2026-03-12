<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ForgotPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    /**
     * Display the forgot password form.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset request.
     */
    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        $validatedData = $request->validated();
        $email = $validatedData['email'];

        // Check if user is archived - prevent archived users from resetting password
        $user = \App\Models\User::where('email', $email)->first();
        if ($user && ! $user->is_active) {
            return back()->withErrors(['email' => 'This account has been deactivated and password reset is not available.']);
        }

        // Use Laravel's native password reset flow
        $status = Password::sendResetLink(['email' => $email]);

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('password_reset_success', __($status));
        }

        // Handle throttled requests specifically
        if ($status === Password::RESET_THROTTLED) {
            return back()->withErrors(['rate_limit' => __($status)]);
        }

        // If there was an error, return back with error
        return back()->withErrors(['email' => __($status)]);
    }
}
