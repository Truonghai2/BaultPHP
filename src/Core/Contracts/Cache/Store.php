<?php

namespace Core\Contracts\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * This is the contract that all cache store implementations must adhere to.
 * It extends the standard PSR-16 CacheInterface to ensure compatibility
 * while also allowing for framework-specific extensions if needed.
 */
interface Store extends CacheInterface
{
    // You can add framework-specific methods here if necessary.
}
