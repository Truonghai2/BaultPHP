<?php

namespace Core\Http\Client\Testing;

use Core\Http\Client\Response;

/**
 * Response Sequence.
 * 
 * Define sequence of fake responses.
 */
class ResponseSequence
{
    private array $responses = [];
    private int $index = 0;

    public function __construct(
        private HttpFake $fake
    ) {
    }

    /**
     * Add response to sequence.
     */
    public function push(mixed $body = [], int $status = 200, array $headers = []): static
    {
        if (is_array($body)) {
            $body = json_encode($body);
            $headers['Content-Type'] = 'application/json';
        }

        $this->responses[] = [
            'body' => $body,
            'status' => $status,
            'headers' => $headers,
        ];

        return $this;
    }

    /**
     * Add successful response.
     */
    public function pushOk(mixed $body = []): static
    {
        return $this->push($body, 200);
    }

    /**
     * Add error response.
     */
    public function pushError(mixed $body = [], int $status = 500): static
    {
        return $this->push($body, $status);
    }

    /**
     * Add not found response.
     */
    public function pushNotFound(mixed $body = []): static
    {
        return $this->push($body, 404);
    }

    /**
     * Add unauthorized response.
     */
    public function pushUnauthorized(mixed $body = []): static
    {
        return $this->push($body, 401);
    }

    /**
     * Get next response in sequence.
     */
    public function next(): Response
    {
        if (empty($this->responses)) {
            throw new \RuntimeException("No more responses in sequence");
        }

        $response = $this->responses[$this->index % count($this->responses)];
        $this->index++;

        $psrResponse = new \GuzzleHttp\Psr7\Response(
            $response['status'],
            $response['headers'],
            $response['body']
        );

        return new Response($psrResponse);
    }

    /**
     * Check if has more responses.
     */
    public function hasMore(): bool
    {
        return $this->index < count($this->responses);
    }

    /**
     * Get HTTP fake instance.
     */
    public function fake(): HttpFake
    {
        return $this->fake;
    }
}
