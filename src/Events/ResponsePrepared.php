<?php

namespace App\Events;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ResponsePrepared
 *
 * This event is dispatched right before a response is sent.
 * It's a good place to modify the response, like injecting the debug bar.
 */
class ResponsePrepared
{
    public function __construct(
        public Request $request,
        public Response $response,
    ) {
    }
}
