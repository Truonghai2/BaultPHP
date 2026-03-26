<?php

namespace Core\Http\Client\Testing;

use Core\Http\Client\HttpClient;
use Core\Http\Client\Response;
use GuzzleHttp\Psr7\Response as PsrResponse;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * HTTP Fake for Testing.
 * 
 * Replaces real HTTP client and provides assertions.
 * 
 * Usage:
 * ```php
 * $fake = HttpFake::create();
 * $fake->fakeSequence()
 *     ->push(['id' => 1], 200)
 *     ->push(['error' => 'Not found'], 404);
 * 
 * $response = $fake->get('https://api.example.com/users/1');
 * $fake->assertSent('https://api.example.com/users/1');
 * ```
 */
class HttpFake extends HttpClient
{
    private array $requests = [];
    private array $responses = [];
    private int $responseIndex = 0;

    public function __construct()
    {
        // Don't call parent constructor
    }

    /**
     * Create HTTP fake instance.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Fake response for specific URL.
     */
    public function fake(string $url, mixed $body = [], int $status = 200, array $headers = []): static
    {
        $this->responses[$url] = $this->createResponse($body, $status, $headers);
        return $this;
    }

    /**
     * Fake sequence of responses.
     */
    public function fakeSequence(): ResponseSequence
    {
        $sequence = new ResponseSequence($this);
        $this->responses['*'] = $sequence;
        return $sequence;
    }

    /**
     * Fake all requests with same response.
     */
    public function fakeAll(mixed $body = [], int $status = 200, array $headers = []): static
    {
        $this->responses['*'] = $this->createResponse($body, $status, $headers);
        return $this;
    }

    /**
     * Override send method.
     */
    public function send(string $method, string $url, array $options = []): Response
    {
        // Record request
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'options' => $options,
            'time' => microtime(true),
        ];

        // Find matching response
        $response = $this->findResponse($url);

        if ($response === null) {
            throw new \RuntimeException("No fake response configured for: {$url}");
        }

        if ($response instanceof ResponseSequence) {
            return $response->next();
        }

        return $response;
    }

    /**
     * Find fake response for URL.
     */
    protected function findResponse(string $url): Response|ResponseSequence|null
    {
        // Exact match
        if (isset($this->responses[$url])) {
            return $this->responses[$url];
        }

        // Pattern match
        foreach ($this->responses as $pattern => $response) {
            if ($pattern === '*' || $this->matchesPattern($url, $pattern)) {
                return $response;
            }
        }

        return null;
    }

    /**
     * Check if URL matches pattern.
     */
    protected function matchesPattern(string $url, string $pattern): bool
    {
        // Simple wildcard matching
        $pattern = str_replace('*', '.*', preg_quote($pattern, '/'));
        return preg_match("/^{$pattern}$/", $url) === 1;
    }

    /**
     * Create fake response.
     */
    protected function createResponse(mixed $body, int $status, array $headers): Response
    {
        if (is_array($body)) {
            $body = json_encode($body);
            $headers['Content-Type'] = 'application/json';
        }

        $psrResponse = new PsrResponse($status, $headers, $body);
        return new Response($psrResponse);
    }

    /**
     * Assert request was sent.
     */
    public function assertSent(string $url, ?callable $callback = null): void
    {
        $count = $this->countRequests($url, $callback);

        PHPUnit::assertTrue(
            $count > 0,
            "No HTTP request was sent to [{$url}]."
        );
    }

    /**
     * Assert request was not sent.
     */
    public function assertNotSent(string $url, ?callable $callback = null): void
    {
        $count = $this->countRequests($url, $callback);

        PHPUnit::assertTrue(
            $count === 0,
            "HTTP request was sent to [{$url}] {$count} time(s)."
        );
    }

    /**
     * Assert nothing was sent.
     */
    public function assertNothingSent(): void
    {
        $count = count($this->requests);

        PHPUnit::assertEquals(
            0,
            $count,
            "{$count} HTTP request(s) were sent."
        );
    }

    /**
     * Assert request count.
     */
    public function assertSentCount(int $count): void
    {
        PHPUnit::assertCount(
            $count,
            $this->requests,
            "Expected {$count} HTTP request(s), but " . count($this->requests) . " were sent."
        );
    }

    /**
     * Count matching requests.
     */
    protected function countRequests(string $url, ?callable $callback = null): int
    {
        $count = 0;

        foreach ($this->requests as $request) {
            if ($request['url'] !== $url) {
                continue;
            }

            if ($callback === null || $callback($request)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get all recorded requests.
     */
    public function recorded(): array
    {
        return $this->requests;
    }

    /**
     * Clear recorded requests.
     */
    public function clear(): void
    {
        $this->requests = [];
        $this->responses = [];
        $this->responseIndex = 0;
    }
}
