<?php

namespace Http\Controllers;

use Core\Contracts\Http\HttpClientInterface;
use Http\ResponseFactory;
use Swoole\Coroutine;
use Swoole\Coroutine\WaitGroup;

class ExternalApiController
{
    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    /**
     * Fetches data from two external APIs concurrently.
     */
    public function fetch(ResponseFactory $responseFactory): \Http\JsonResponse
    {
        $results = [];
        $wg = new WaitGroup();

        // Start two coroutines to fetch data concurrently
        $wg->add(2);

        Coroutine::create(function () use (&$results, $wg) {
            $results['posts'] = json_decode($this->httpClient->get('https://jsonplaceholder.typicode.com/posts/1')->getContent(), true);
            $wg->done();
        });

        Coroutine::create(function () use (&$results, $wg) {
            $results['comments'] = json_decode($this->httpClient->get('https://jsonplaceholder.typicode.com/comments/1')->getContent(), true);
            $wg->done();
        });

        $wg->wait(); // Wait for both requests to complete

        return $responseFactory->json($results);
    }
}
