<?php

namespace Core\Routing\Attributes;

use Attribute;

/**
 * Defines rate limiting rules for a controller method.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class RateLimit
{
    /**
     * @param int $maxAttempts The maximum number of attempts allowed.
     * @param int $decayMinutes The duration in minutes for which the attempts are counted.
     */
    public function __construct(
        public int $maxAttempts,
        public int $decayMinutes = 1,
    ) {
    }
}
