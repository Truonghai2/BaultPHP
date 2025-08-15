<?php

namespace Http;

use Psr\Http\Message\ResponseInterface;

/**
 * Sends a PSR-7 response to the browser.
 */
class ResponseEmitter
{
    public function emit(ResponseInterface $response): void
    {
        if (headers_sent()) {
            return;
        }

        header(sprintf(
            'HTTP/%s %s %s',
            $response->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getReasonPhrase(),
        ), true, $response->getStatusCode());

        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }

        echo $response->getBody();
    }
}
