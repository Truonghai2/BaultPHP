<?php

namespace Core\Exceptions;

use Psr\Container\ContainerExceptionInterface;

/**
 * Base exception for the container, thrown for any other error.
 */
class ContainerException extends \Exception implements ContainerExceptionInterface
{
}
