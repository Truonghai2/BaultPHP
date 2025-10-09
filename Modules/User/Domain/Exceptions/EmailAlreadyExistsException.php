<?php

declare(strict_types=1);

namespace Modules\User\Domain\Exceptions;

use Exception;
use Throwable;

/**
 * Exception được ném ra khi cố gắng sử dụng một email đã tồn tại.
 */
class EmailAlreadyExistsException extends Exception
{
    public function __construct(string $message = 'The provided email already exists.', int $code = 409, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
