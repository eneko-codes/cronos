<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Thrown when an API connection error occurs (e.g., network failure, timeout).
 * Used by all API clients for consistent error handling.
 */
class ApiConnectionException extends Exception
{
    /**
     * Report the exception (centralized logging).
     */
    public function report(): void
    {
        Log::error('[API Connection Exception] '.$this->getMessage(), [
            'exception' => static::class,
            'code' => $this->getCode(),
        ]);
    }
}
