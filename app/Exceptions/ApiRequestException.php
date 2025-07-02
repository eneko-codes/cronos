<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Thrown when an API request error occurs (e.g., invalid request, authentication failure).
 * Used by all API clients for consistent error handling.
 */
class ApiRequestException extends Exception
{
    /**
     * Report the exception (centralized logging).
     */
    public function report(): void
    {
        Log::error('[API Request Exception] '.$this->getMessage(), [
            'exception' => static::class,
            'code' => $this->getCode(),
        ]);
    }
}
