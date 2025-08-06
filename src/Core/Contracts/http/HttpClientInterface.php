<?php

namespace Core\Contracts\Http;

/**
 * Interface HttpClientInterface
 * Defines a contract for making HTTP requests.
 */
interface HttpClientInterface
{
    /**
     * Send a GET request to the given URL.
     *
     * @param string $url
     * @param array $options
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function get(string $url, array $options = []): \Http\JsonResponse;

    /**
     * Send a POST request to the given URL.
     *
     * @param string $url
     * @param array $data
     * @param array $options
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function post(string $url, array $data = [], array $options = []): \Http\JsonResponse;
}
