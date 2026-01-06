<?php

namespace Core\Debug;

use Core\Cookie\CookieJar;
use Psr\Http\Message\ResponseInterface;

/**
 * Cookie jar wrapper với real-time broadcasting.
 */
class RealtimeTraceableCookieJar extends CookieJar
{
    protected DebugBroadcaster $broadcaster;

    public function setBroadcaster(DebugBroadcaster $broadcaster): void
    {
        $this->broadcaster = $broadcaster;
    }

    /**
     * Queue a cookie to be sent with the response.
     */
    public function queue(
        string $name,
        string $value,
        int $minutes = 0,
        ?string $path = null,
        ?string $domain = null,
        ?bool $secure = null,
        bool $httpOnly = true,
        bool $raw = false,
        ?string $sameSite = null,
    ): void {
        parent::queue($name, $value, $minutes, $path, $domain, $secure, $httpOnly, $raw, $sameSite);

        if (isset($this->broadcaster) && $this->broadcaster->isEnabled()) {
            $this->broadcaster->broadcastCookie('QUEUE', $name, $value, [
                'minutes' => $minutes,
                'path' => $path,
                'domain' => $domain,
                'secure' => $secure,
                'httpOnly' => $httpOnly,
                'sameSite' => $sameSite,
            ]);
        }
    }

    /**
     * Queue a cookie to be expired.
     */
    public function expire(string $name, ?string $path = null, ?string $domain = null): void
    {
        parent::expire($name, $path, $domain);

        if (isset($this->broadcaster) && $this->broadcaster->isEnabled()) {
            $this->broadcaster->broadcastCookie('EXPIRE', $name, null, [
                'path' => $path,
                'domain' => $domain,
            ]);
        }
    }

    /**
     * Add queued cookies to response.
     */
    public function addQueuedCookiesToResponse(ResponseInterface $response): ResponseInterface
    {
        $response = parent::addQueuedCookiesToResponse($response);

        if (isset($this->broadcaster) && $this->broadcaster->isEnabled()) {
            $this->broadcaster->broadcastCookie('ADDED_TO_RESPONSE', 'queued_cookies', count($this->queued));
        }

        return $response;
    }
}
