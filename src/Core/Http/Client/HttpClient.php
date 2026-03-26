<?php

namespace Core\Http\Client;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * HTTP Client.
 * 
 * Fluent wrapper around Guzzle for making HTTP requests to external APIs.
 * 
 * Features:
 * - Fluent API
 * - Middleware support (retry, logging, auth)
 * - Request/Response builders
 * - Testing utilities
 * - Timeout & error handling
 * 
 * Usage:
 * ```php
 * $response = Http::get('https://api.example.com/users');
 * $data = $response->json();
 * 
 * $response = Http::post('https://api.example.com/users', [
 *     'name' => 'John',
 *     'email' => 'john@example.com',
 * ]);
 * ```
 */
class HttpClient
{
    private GuzzleClient $client;
    private array $options = [];
    private array $headers = [];
    private array $middleware = [];
    private ?LoggerInterface $logger = null;

    public function __construct(?LoggerInterface $logger = null, array $config = [])
    {
        $this->logger = $logger;
        $this->options = $config;
        
        // Create handler stack for middleware
        $stack = HandlerStack::create();
        $this->options['handler'] = $stack;
        
        $this->client = new GuzzleClient($this->options);
    }

    /**
     * Set base URI.
     */
    public function baseUrl(string $url): static
    {
        $this->options['base_uri'] = $url;
        return $this;
    }

    /**
     * Set request timeout (seconds).
     */
    public function timeout(int $seconds): static
    {
        $this->options['timeout'] = $seconds;
        return $this;
    }

    /**
     * Set connect timeout (seconds).
     */
    public function connectTimeout(int $seconds): static
    {
        $this->options['connect_timeout'] = $seconds;
        return $this;
    }

    /**
     * Add header.
     */
    public function withHeader(string $name, string $value): static
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Add multiple headers.
     */
    public function withHeaders(array $headers): static
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * Set Bearer token.
     */
    public function withToken(string $token, string $type = 'Bearer'): static
    {
        return $this->withHeader('Authorization', "{$type} {$token}");
    }

    /**
     * Set Basic auth.
     */
    public function withBasicAuth(string $username, string $password): static
    {
        $this->options['auth'] = [$username, $password];
        return $this;
    }

    /**
     * Set Digest auth.
     */
    public function withDigestAuth(string $username, string $password): static
    {
        $this->options['auth'] = [$username, $password, 'digest'];
        return $this;
    }

    /**
     * Set cookies.
     */
    public function withCookies(array $cookies): static
    {
        $this->options['cookies'] = $cookies;
        return $this;
    }

    /**
     * Allow redirects.
     */
    public function withRedirects(int $max = 5): static
    {
        $this->options['allow_redirects'] = [
            'max' => $max,
            'strict' => true,
            'referer' => true,
        ];
        return $this;
    }

    /**
     * Disable SSL verification (not recommended for production).
     */
    public function withoutVerifying(): static
    {
        $this->options['verify'] = false;
        return $this;
    }

    /**
     * Set proxy.
     */
    public function withProxy(string $proxy): static
    {
        $this->options['proxy'] = $proxy;
        return $this;
    }

    /**
     * Add retry middleware.
     */
    public function retry(int $times = 3, int $delayMs = 100): static
    {
        $this->middleware[] = new Middleware\RetryMiddleware($times, $delayMs);
        return $this;
    }

    /**
     * Add logging middleware.
     */
    public function withLogging(): static
    {
        if ($this->logger) {
            $this->middleware[] = new Middleware\LoggingMiddleware($this->logger);
        }
        return $this;
    }

    /**
     * GET request.
     */
    public function get(string $url, array $query = []): Response
    {
        return $this->send('GET', $url, [
            'query' => $query,
        ]);
    }

    /**
     * POST request.
     */
    public function post(string $url, array $data = []): Response
    {
        return $this->send('POST', $url, [
            'json' => $data,
        ]);
    }

    /**
     * PUT request.
     */
    public function put(string $url, array $data = []): Response
    {
        return $this->send('PUT', $url, [
            'json' => $data,
        ]);
    }

    /**
     * PATCH request.
     */
    public function patch(string $url, array $data = []): Response
    {
        return $this->send('PATCH', $url, [
            'json' => $data,
        ]);
    }

    /**
     * DELETE request.
     */
    public function delete(string $url, array $data = []): Response
    {
        return $this->send('DELETE', $url, [
            'json' => $data,
        ]);
    }

    /**
     * HEAD request.
     */
    public function head(string $url): Response
    {
        return $this->send('HEAD', $url);
    }

    /**
     * Send form data (multipart/form-data).
     */
    public function asForm(): static
    {
        $this->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        return $this;
    }

    /**
     * Send JSON data.
     */
    public function asJson(): static
    {
        $this->withHeader('Content-Type', 'application/json');
        $this->withHeader('Accept', 'application/json');
        return $this;
    }

    /**
     * Upload file.
     */
    public function attach(string $name, string $contents, ?string $filename = null): static
    {
        if (!isset($this->options['multipart'])) {
            $this->options['multipart'] = [];
        }

        $this->options['multipart'][] = [
            'name' => $name,
            'contents' => $contents,
            'filename' => $filename,
        ];

        return $this;
    }

    /**
     * Send custom request.
     */
    public function send(string $method, string $url, array $options = []): Response
    {
        // Merge headers
        if (!empty($this->headers)) {
            $options['headers'] = array_merge(
                $options['headers'] ?? [],
                $this->headers
            );
        }

        // Merge options
        $options = array_merge($this->options, $options);

        try {
            $startTime = microtime(true);

            if ($this->logger) {
                $this->logger->debug("HTTP Request", [
                    'method' => $method,
                    'url' => $url,
                    'headers' => $options['headers'] ?? [],
                ]);
            }

            $response = $this->client->request($method, $url, $options);

            $duration = (microtime(true) - $startTime) * 1000;

            if ($this->logger) {
                $this->logger->info("HTTP Response", [
                    'method' => $method,
                    'url' => $url,
                    'status' => $response->getStatusCode(),
                    'duration_ms' => round($duration, 2),
                ]);
            }

            return new Response($response);
        } catch (GuzzleException $e) {
            if ($this->logger) {
                $this->logger->error("HTTP Request failed", [
                    'method' => $method,
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }

            throw new HttpException("HTTP request failed: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Send async request.
     */
    public function sendAsync(string $method, string $url, array $options = []): \GuzzleHttp\Promise\PromiseInterface
    {
        // Merge headers
        if (!empty($this->headers)) {
            $options['headers'] = array_merge(
                $options['headers'] ?? [],
                $this->headers
            );
        }

        // Merge options
        $options = array_merge($this->options, $options);

        return $this->client->requestAsync($method, $url, $options);
    }

    /**
     * Get underlying Guzzle client.
     */
    public function getClient(): GuzzleClient
    {
        return $this->client;
    }
}
