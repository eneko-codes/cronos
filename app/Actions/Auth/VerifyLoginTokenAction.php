<?php

namespace App\Actions\Auth;

use App\Exceptions\InvalidLoginTokenException;
use App\Exceptions\LoginTokenExpiredException;
use App\Models\LoginToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

/**
 * Action class responsible for verifying a magic login token and authenticating the user.
 */
class VerifyLoginTokenAction
{
    /**
     * Verifies the provided magic login token.
     *
     * Checks for token existence and expiry. If valid, authenticates the associated user,
     * deletes the token to prevent reuse, logs the event, and returns the user.
     * Throws specific exceptions for invalid or expired tokens.
     *
     * @param  string  $token  The raw (unhashed) login token from the magic link.
     * @param  bool  $remember  Whether to persist the login session ("Remember Me").
     * @param  string  $ipAddress  The IP address of the verifying user.
     * @param  string|null  $userAgent  The user agent string of the verifying user.
     * @return User The authenticated user instance.
     *
     * @throws InvalidLoginTokenException if the token is not found or has already been used.
     * @throws LoginTokenExpiredException if the token exists but has expired.
     */
    public function handle(string $token, bool $remember, string $ipAddress, ?string $userAgent): User
    {

        // Hash the incoming token to compare against the stored hash.
        $hashedToken = hash('sha256', $token);
        // Eager load the user relationship for efficiency.
        $loginToken = LoginToken::with('user')->where('token', $hashedToken)->first();

        // If no token record matches the hash, it's invalid or was already used.
        if (! $loginToken) {
            throw new InvalidLoginTokenException('Token not found or already used.', [
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);
        }

        $user = $loginToken->user;

        // Check if the token's expiry time has passed.
        if (Carbon::now()->greaterThan($loginToken->expires_at)) {
            // IMPORTANT: Delete the expired token immediately to prevent reuse.
            $loginToken->delete();
            // Throw specific exception for expired tokens.
            throw new LoginTokenExpiredException('The token has expired.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'token_expires_at' => $loginToken->expires_at->toIso8601String(),
            ]);
        }

        // If token is valid and not expired, log the user in and delete the token.
        // Use a transaction to ensure both operations succeed or fail together.
        DB::transaction(function () use ($loginToken, $user, $remember) {
            // Log the user in, applying the 'remember' preference stored with the token.
            Auth::login($user, $remember); // Use stored remember value

            // Update session timestamp for activity tracking.
            Session::put('last_activity', Carbon::now()->timestamp);

            // Delete the token now that it has been successfully used.
            $loginToken->delete();
        });

        // Log the successful authentication event
        // Note: We need the session ID. Since the action runs before the controller returns
        // the response, we can get the current session ID here.
        Log::info('User authenticated successfully via token: '.$user->name, [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'session_id' => Session::getId(), // Get session ID after login
            'remember_me' => $remember,
        ]);

        // Return the authenticated user object.
        return $user;
    }
}
