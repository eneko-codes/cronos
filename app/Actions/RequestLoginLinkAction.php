<?php

declare(strict_types=1);

namespace App\Actions;

use App\Mail\LoginEmail;
use App\Models\LoginToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Action class responsible for handling the request for a magic login link.
 */
class RequestLoginLinkAction
{
    /**
     * Handles the request to generate and send a magic login link.
     *
     * Finds the user by email. If found, generates a secure token, stores its hash,
     * logs the attempt, generates a signed URL, and queues an email containing the link.
     *
     * @param  string  $email  The email address provided by the user.
     * @param  bool  $remember  Whether the user checked the "Remember Me" option.
     * @param  string  $ipAddress  The IP address of the requesting user.
     * @param  string|null  $userAgent  The user agent string of the requesting user.
     * @return bool True if the user was found and the email was queued, false otherwise.
     */
    public function handle(string $email, bool $remember, string $ipAddress, ?string $userAgent): bool
    {
        // Define token validity duration in minutes
        $tokenValidityMinutes = config('auth.login_token.expire_minutes', 15);

        // Check if user exists.
        $user = User::where('email', $email)->first();

        // If user doesn't exist, log and return false.
        if (! $user) {
            Log::info(
                'Magic link requested for non-existent email: '.$email,
                [
                    'email' => $email,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                ]
            );

            return false;
        }

        // Log successful magic link request.
        Log::info('Magic link requested for user: '.$user->name, [
            'email' => $email,
            'name' => $user->name,
            'user_id' => $user->id,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        // Generate a secure random token for the magic link.
        $token = Str::random(60);

        // Securely store the hashed token, associating it with the user and setting an expiry.
        // Also stores the 'remember' preference for when the token is verified.
        LoginToken::updateOrCreate(
            ['user_id' => $user->id],
            [
                'token' => hash('sha256', $token),
                'expires_at' => Carbon::now()->addMinutes($tokenValidityMinutes),
                'remember' => $remember,
            ]
        );

        // Generate the temporary signed URL for the magic link
        $url = URL::temporarySignedRoute(
            'login.verify',
            Carbon::now()->addMinutes($tokenValidityMinutes),
            [
                'token' => $token,
                'remember' => $remember ? '1' : '0',
            ]
        );

        // Dispatch the job to send the email in the background.
        Mail::to($user->email)->queue(new LoginEmail($user, $url));

        // Return true to indicate the email job was dispatched successfully.
        return true;
    }
}
