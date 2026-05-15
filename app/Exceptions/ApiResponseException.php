<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Thrown when an API response error occurs (e.g., error returned by the remote API).
 * Used by all API clients for consistent error handling.
 */
class ApiResponseException extends Exception
{
    /**
     * Report the exception (centralized logging).
     */
    public function report(): void
    {
        Log::error('[API Response Exception] '.$this->getMessage(), [
            'exception' => static::class,
            'code' => $this->getCode(),
        ]);
    }
}
