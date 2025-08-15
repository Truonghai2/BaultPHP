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
     * @return \Core\Http\Response
     */
    public function get(string $url, array $options = []): \Core\Http\Response;

    /**
     * Send a POST request to the given URL.
     *
     * @param string $url
     * @param array $data
     * @param array $options
     * @return \Core\Http\Response
     */
    public function post(string $url, array $data = [], array $options = []): \Core\Http\Response;
}
