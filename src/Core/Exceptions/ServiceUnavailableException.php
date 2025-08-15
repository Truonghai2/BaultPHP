<?php

namespace Core\Exceptions;

use RuntimeException;

/**
 * Thrown when a service is unavailable due to an open circuit breaker.
 */
class ServiceUnavailableException extends RuntimeException
{
}
