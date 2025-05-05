<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\LogoutUserAction;
use App\Actions\Auth\VerifyLoginTokenAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyLoginTokenRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controller handling the magic link verification and user logout processes.
 */
class LoginController extends Controller
{
    /**
     * Verifies the magic login token and authenticates the user.
     *
     * This method receives the validated request, extracts necessary data,
     * and delegates the core verification logic to the VerifyLoginTokenAction.
     * It relies on the action or form request exception handling for error feedback.
     * On success, redirects the authenticated user to the dashboard.
     *
     * @param  VerifyLoginTokenRequest  $request  The validated request containing the token and remember flag.
     * @param  VerifyLoginTokenAction  $verifyLoginTokenAction  The action performing the verification.
     * @return RedirectResponse A redirection to the dashboard on success, or back to login on failure (handled by exceptions).
     */
    public function verify(VerifyLoginTokenRequest $request, VerifyLoginTokenAction $verifyLoginTokenAction): RedirectResponse
    {
        // Retrieve IP and UserAgent for the action using the request helper
        $ipAddress = request()->ip();
        $userAgent = request()->header('User-Agent');

        // Validation is handled by VerifyLoginTokenRequest

        // Retrieve validated data
        $validated = $request->validated();
        $token = $validated['token'];
        // Explicitly cast remember to boolean
        $remember = (bool) ($validated['remember'] ?? false);

        // Delegate the core token verification and user login logic to the Action.
        // If the action throws InvalidLoginTokenException or LoginTokenExpiredException,
        // their respective render methods will handle the redirect and error message.
        // Any other unexpected exception will be handled by Laravel's default handler.
        $user = $verifyLoginTokenAction->handle($token, $remember, $ipAddress, $userAgent);

        // Redirect authenticated user to the dashboard.
        return redirect()
            ->route('dashboard')
            ->with('success', 'You have successfully logged in.');
    }

    /**
     * Handles the user logout request.
     *
     * Delegates the core logout logic (session invalidation, token regeneration, logging)
     * to the LogoutUserAction and then redirects the user to the login page.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  LogoutUserAction  $logoutUserAction  The action performing the logout.
     * @return RedirectResponse A redirection to the login page.
     */
    public function logout(Request $request, LogoutUserAction $logoutUserAction): RedirectResponse
    {
        // Execute the logout action
        $logoutUserAction->handle($request);

        // Redirect to the login page with a success message
        return redirect()
            ->route('login')
            ->with('success', 'You have been logged out successfully.');
    }
}
