<?php

namespace Http;

use Psr\Http\Message\ResponseInterface;
use Swoole\Coroutine\Http\Client;

/**
 * A non-blocking HTTP client implementation using Swoole Coroutines.
 */
class SwooleHttpClient implements HttpClientInterface
{
    public function get(string $url, array $options = []): Response
    {
        $client = $this->createClient($url);
        $client->get($this->getPath($url));
        $response = $this->buildResponse($client);
        $client->close();

        return $response;
    }

    public function post(string $url, array $data = [], array $options = []): Response
    {
        $client = $this->createClient($url);
        $client->post($this->getPath($url), $data);
        $response = $this->buildResponse($client);
        $client->close();

        return $response;
    }

    private function createClient(string $url): Client
    {
        $parts = parse_url($url);
        $host = $parts['host'];
        $port = $parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80);
        $ssl = $parts['scheme'] === 'https';

        return new Client($host, $port, $ssl);
    }

    private function getPath(string $url): string
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '/';
        if (!empty($parts['query'])) {
            $path .= '?' . $parts['query'];
        }
        return $path;
    }

    private function buildResponse(Client $client): Response
    {
        $response = new Response();
        $response->setContent($client->body ?? '');
        $response->setStatusCode($client->statusCode);

        if (is_array($client->headers)) {
            $response->setHeaders($client->headers);
        }

        return $response;
    }
}

