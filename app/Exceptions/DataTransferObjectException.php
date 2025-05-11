<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Base exception class for DataTransferObject related errors.
 * This exception is thrown when there are issues with DTO validation, creation, or processing.
 */
class DataTransferObjectException extends Exception
{
    /**
     * Create a new DataTransferObjectException instance.
     *
     * @param  string  $message  The error message
     * @param  int  $code  The error code
     * @param  \Exception|null  $previous  The previous exception
     */
    public function __construct(string $message = '', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
