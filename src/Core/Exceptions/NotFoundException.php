<?php

namespace Core\Exceptions;

use Psr\Container\NotFoundExceptionInterface;

/**
 * Exception thrown when an identifier is not found in the container.
 */
class NotFoundException extends \Exception implements NotFoundExceptionInterface
{
}
