<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RequestLoginLinkAction;
use App\Actions\VerifyLoginTokenAction;
use App\Http\Requests\LoginLinkRequest;
use App\Http\Requests\VerifyLoginTokenRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth as AuthFacade;
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
     * Handle an incoming magic link request.
     */
    public function store(LoginLinkRequest $loginRequest, RequestLoginLinkAction $requestLoginLinkAction): RedirectResponse
    {
        $validatedLoginData = $loginRequest->validated();
        $email = $validatedLoginData['email'];
        $remember = filter_var($validatedLoginData['remember'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $ipAddress = request()->ip();
        $userAgent = request()->header('User-Agent');

        $linkSent = $requestLoginLinkAction->handle(
            $email,
            $remember,
            $ipAddress,
            $userAgent
        );

        if (! $linkSent) {
            return back()->withInput(['email' => $email, 'remember' => $remember])->withErrors([
                'email' => 'The provided email does not match our records.',
            ]);
        }

        return back()->with('status', 'Click on the login link we sent to your email.');
    }

    /**
     * Verify the magic link token and log the user in.
     */
    public function verify(VerifyLoginTokenRequest $loginRequest, VerifyLoginTokenAction $verifyLoginTokenAction): RedirectResponse
    {
        $validatedLoginData = $loginRequest->validated();
        $token = $validatedLoginData['token'];
        $remember = filter_var($validatedLoginData['remember'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $verifyLoginTokenAction->handle(
            $token,
            $remember,
            request()->ip(),
            request()->header('User-Agent')
        );

        request()->session()->regenerate();

        return redirect()->intended('/dashboard');
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $httpRequest): RedirectResponse
    {
        AuthFacade::logout();

        $httpRequest->session()->invalidate();
        $httpRequest->session()->regenerateToken();

        return redirect('/');
    }
}
