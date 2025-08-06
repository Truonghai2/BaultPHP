<?php

namespace App\Exceptions;

class NotFoundException extends \Exception
{
    /**
     * Create a new NotFoundException instance.
     *
     * @param string $message
     * @param int $code
     */
    public function __construct(string $message = 'Resource not found.', int $code = 404)
    {
        parent::__construct($message, $code);
    }
}
