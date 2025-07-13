<?php

namespace App\Exceptions;

use Exception;

class TokenMismatchException extends Exception
{
    public function __construct(string $message = 'CSRF token mismatch.', int $code = 419, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}