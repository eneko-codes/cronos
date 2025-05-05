<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Exception thrown when a login token exists but has expired.
 */
class LoginTokenExpiredException extends Exception
{
    protected array $context;

    /**
     * Constructor allows passing context for logging.
     */
    public function __construct(string $message = 'Login token expired.', array $context = [], int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Report the exception.
     */
    public function report(): void
    {
        // Log the failure with context.
        Log::info('Token verification failed - '.$this->getMessage(), $this->context);
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request): RedirectResponse
    {
        // Redirect the user back to the login page with the specific error message.
        return redirect()
            ->route('login')
            ->withErrors(['token' => 'The login link has expired. Please request a new one.']);
    }
}
