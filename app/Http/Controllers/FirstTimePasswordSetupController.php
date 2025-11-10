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
use Illuminate\View\View;

class FirstTimePasswordSetupController extends Controller
{
    /**
     * Display the first-time password setup form.
     */
    public function create(Request $request): View
    {
        $email = $request->get('email');
        $token = $request->get('token');

        // Validate token for security
        if (! $token) {
            abort(403, 'Invalid setup link. Please use the link from your welcome email.');
        }

        $user = User::where('email', $email)->whereNull('password')->first();

        if (! $user) {
            abort(404, 'User not found or already has a password set.');
        }

        // Verify the token matches what was sent in the welcome email
        // For now, we'll use a simple hash of user ID + email + created_at
        $expectedToken = hash('sha256', $user->id.$user->email.$user->created_at->toDateTimeString());

        if (! hash_equals($expectedToken, $token)) {
            abort(403, 'Invalid setup link. Please use the link from your welcome email.');
        }

        return view('auth.first-time-password-setup', [
            'email' => $email,
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * Handle the first-time password setup request.
     */
    public function store(FirstTimePasswordSetupRequest $request): RedirectResponse
    {
        $validatedData = $request->validated();
        $email = $validatedData['email'];
        $token = $validatedData['token'];
        $password = $validatedData['password'];

        $user = User::where('email', $email)->whereNull('password')->first();

        if (! $user) {
            return back()->withErrors([
                'email' => 'User not found or already has a password set.',
            ]);
        }

        // Verify the token for security
        $expectedToken = hash('sha256', $user->id.$user->email.$user->created_at->toDateTimeString());

        if (! hash_equals($expectedToken, $token)) {
            return back()->withErrors([
                'email' => 'Invalid setup link. Please use the link from your welcome email.',
            ]);
        }

        // Set the user's password
        $user->password = Hash::make($password);
        $user->save();

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

        return redirect()->route('dashboard')->with('password_setup_success', 'Password set successfully! You are now logged in.');
    }
}
