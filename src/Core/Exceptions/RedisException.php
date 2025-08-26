<?php

namespace Core\Exceptions;

use RuntimeException;

/**
 * Represents an error that occurred during a Redis operation.
 * This is a wrapper around lower-level Redis exceptions to provide
 * a consistent exception type within the application.
 */
class RedisException extends RuntimeException
{
}
