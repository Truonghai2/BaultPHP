<?php

namespace Core\Auth\Events;

/**
 * This event is dispatched when a potential "remember me" cookie theft is detected.
 * This happens when a valid selector is presented with an invalid verifier.
 */
class CookieTheftDetected
{
    /**
     * @param int $userId The ID of the user whose account may be compromised.
     */
    public function __construct(public int $userId)
    {
    }
}
