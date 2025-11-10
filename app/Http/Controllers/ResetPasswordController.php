<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ResetPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class ResetPasswordController extends Controller
{
    /**
     * Display the password reset form.
     *
     * Security: Email comes from URL parameter (from reset link), not user input.
     * This prevents users from changing the email to reset someone else's password.
     */
    public function create(Request $request): View
    {
        $token = $request->route('token');
        $email = $request->get('email');

        // Check if token is still valid
        // Laravel stores hashed tokens, so we need to check all tokens for this email
        $tokenRecord = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        $tokenExists = $tokenRecord && Hash::check($token, $tokenRecord->token);

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $email,
            'tokenValid' => $tokenExists,
        ]);
    }

    /**
     * Handle an incoming new password request.
     */
    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        $validatedData = $request->validated();

        // Attempt to reset the user's password
        $status = Password::reset(
            $validatedData,
            function ($user, $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('password_reset_success', __($status));
        }

        return back()->withErrors(['email' => [__($status)]]);
    }
}
