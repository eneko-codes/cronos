<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Notifications\WelcomeNewUserEmail;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth as AuthFacade;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming login request.
     */
    public function store(LoginRequest $loginRequest): RedirectResponse
    {
        // Regenerate session to prevent session fixation attacks
        request()->session()->regenerate();

        $validatedLoginData = $loginRequest->validated();
        $email = $validatedLoginData['email'];
        $password = $validatedLoginData['password'];
        $remember = (bool) ($validatedLoginData['remember'] ?? false);

        $ipAddress = request()->ip();
        $userAgent = request()->header('User-Agent');

        // Check if user exists but doesn't have a password set
        $user = User::where('email', $email)->first();
        if ($user && is_null($user->password)) {
            // Resend welcome email instead of allowing direct access
            $user->notify(new WelcomeNewUserEmail);

            return back()->with('welcome_email_sent', 'Please check your inbox at '.$email.' and click the link to create your password.');
        }

        // Attempt to authenticate the user
        if (AuthFacade::attempt(['email' => $email, 'password' => $password], $remember)) {
            $user = AuthFacade::user();

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

            return redirect()->intended(route('dashboard'));
        }

        return back()->withInput(['email' => $email, 'remember' => $remember])->withErrors([
            'credentials' => 'These credentials do not match our records.',
        ]);
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $httpRequest): RedirectResponse
    {
        $user = AuthFacade::user();
        $ipAddress = $httpRequest->ip();
        $userAgent = $httpRequest->header('User-Agent');
        $sessionId = $httpRequest->session()->getId();

        AuthFacade::logout();

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
