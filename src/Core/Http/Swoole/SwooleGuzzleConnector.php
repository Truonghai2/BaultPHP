<?php

namespace Core\Http\Swoole;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;

/**
 * Creates Guzzle Client instances configured for the Swoole environment.
 *
 * This connector ensures that each Guzzle client uses the SwooleHandler,
 * enabling non-blocking HTTP requests within Swoole coroutines.
 */
class SwooleGuzzleConnector
{
    /**
     * Create a new Guzzle client instance.
     *
     * @param array $config Guzzle client configuration options.
     * @return ClientInterface
     */
    public function connect(array $config): ClientInterface
    {
        $handler = new CurlHandler([
            'options' => [
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
            ],
        ]);
        $stack = HandlerStack::create($handler);
        $config['handler'] = $stack;

        return new Client($config);
    }
}
