<?php

namespace App\Http\Controllers;

use App\Http\ResponseFactory;
use Core\Contracts\Http\HttpClientInterface;
use Swoole\Coroutine;
use Swoole\Coroutine\WaitGroup;

class ExternalApiController
{
    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    public function fetch(ResponseFactory $responseFactory): \Http\JsonResponse
    {
        $results = [];
        $wg = new WaitGroup();

        $wg->add(2);

        Coroutine::create(function () use (&$results, $wg) {
            $results['posts'] = json_decode($this->httpClient->get('https://jsonplaceholder.typicode.com/posts/1')->getContent(), true);
            $wg->done();
        });

        Coroutine::create(function () use (&$results, $wg) {
            $results['comments'] = json_decode($this->httpClient->get('https://jsonplaceholder.typicode.com/comments/1')->getContent(), true);
            $wg->done();
        });

        $wg->wait();

        return $responseFactory->json($results);
    }
}
