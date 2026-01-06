<?php

namespace Core\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * Request wrapper that provides Laravel-like convenience methods
 * on top of PSR-7 ServerRequestInterface
 */
class Request implements ServerRequestInterface
{
    public function __construct(
        private ServerRequestInterface $request
    ) {
    }

    /**
     * Get the path of the request (e.g., /admin/users)
     */
    public function path(): string
    {
        $path = $this->getUri()->getPath();
        return trim($path, '/') ?: '/';
    }

    /**
     * Get the full URL for the request
     */
    public function url(): string
    {
        $uri = $this->getUri();
        $scheme = $uri->getScheme();
        $authority = $uri->getAuthority();
        $path = $uri->getPath();

        $url = '';
        if ($scheme) {
            $url .= $scheme . ':';
        }

        if ($authority) {
            $url .= '//' . $authority;
        }

        $url .= $path;

        return $url;
    }

    /**
     * Get the full URL including query string
     */
    public function fullUrl(): string
    {
        $url = $this->url();
        $query = $this->getUri()->getQuery();

        return $query ? $url . '?' . $query : $url;
    }

    /**
     * Determine if the current request URI matches a pattern
     */
    public function is(string ...$patterns): bool
    {
        $path = $this->path();

        foreach ($patterns as $pattern) {
            if ($this->matchesPattern($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match a pattern against a path
     */
    private function matchesPattern(string $pattern, string $path): bool
    {
        // Convert wildcard pattern to regex
        $pattern = preg_quote($pattern, '#');
        $pattern = str_replace('\*', '.*', $pattern);

        return (bool) preg_match('#^' . $pattern . '$#u', $path);
    }

    /**
     * Get the request method
     */
    public function method(): string
    {
        return $this->getMethod();
    }

    /**
     * Check if the request is the given method
     */
    public function isMethod(string $method): bool
    {
        return strcasecmp($this->getMethod(), $method) === 0;
    }

    /**
     * Get an input value from the request
     */
    public function input(string $key, mixed $default = null): mixed
    {
        $data = array_merge(
            $this->getQueryParams(),
            (array) $this->getParsedBody()
        );

        return $data[$key] ?? $default;
    }

    /**
     * Get all input data
     */
    public function all(): array
    {
        return array_merge(
            $this->getQueryParams(),
            (array) $this->getParsedBody()
        );
    }

    /**
     * Check if the request has a given input key
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    /**
     * Get a header from the request.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return string|null
     */
    public function header(string $key, mixed $default = null): ?string
    {
        $value = $this->getHeaderLine($key);
        return $value !== '' ? $value : $default;
    }

    /**
     * Get the client's IP address.
     *
     * This method checks for common proxy headers first, then falls back to REMOTE_ADDR.
     *
     * @return string|null
     */
    public function ip(): ?string
    {
        $serverParams = $this->request->getServerParams();

        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        return collect($headers)->map(fn($header) => $serverParams[$header] ?? null)->filter()->first();
    }

    // Delegate all PSR-7 methods to the wrapped request

    public function getProtocolVersion(): string
    {
        return $this->request->getProtocolVersion();
    }

    public function withProtocolVersion(string $version): static
    {
        $new = clone $this;
        $new->request = $this->request->withProtocolVersion($version);
        return $new;
    }

    public function getHeaders(): array
    {
        return $this->request->getHeaders();
    }

    public function hasHeader(string $name): bool
    {
        return $this->request->hasHeader($name);
    }

    public function getHeader(string $name): array
    {
        return $this->request->getHeader($name);
    }

    public function getHeaderLine(string $name): string
    {
        return $this->request->getHeaderLine($name);
    }

    public function withHeader(string $name, $value): static
    {
        $new = clone $this;
        $new->request = $this->request->withHeader($name, $value);
        return $new;
    }

    public function withAddedHeader(string $name, $value): static
    {
        $new = clone $this;
        $new->request = $this->request->withAddedHeader($name, $value);
        return $new;
    }

    public function withoutHeader(string $name): static
    {
        $new = clone $this;
        $new->request = $this->request->withoutHeader($name);
        return $new;
    }

    public function getBody(): StreamInterface
    {
        return $this->request->getBody();
    }

    public function withBody(StreamInterface $body): static
    {
        $new = clone $this;
        $new->request = $this->request->withBody($body);
        return $new;
    }

    public function getRequestTarget(): string
    {
        return $this->request->getRequestTarget();
    }

    public function withRequestTarget(string $requestTarget): static
    {
        $new = clone $this;
        $new->request = $this->request->withRequestTarget($requestTarget);
        return $new;
    }

    public function getMethod(): string
    {
        return $this->request->getMethod();
    }

    public function withMethod(string $method): static
    {
        $new = clone $this;
        $new->request = $this->request->withMethod($method);
        return $new;
    }

    public function getUri(): UriInterface
    {
        return $this->request->getUri();
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): static
    {
        $new = clone $this;
        $new->request = $this->request->withUri($uri, $preserveHost);
        return $new;
    }

    public function getServerParams(): array
    {
        return $this->request->getServerParams();
    }

    public function getCookieParams(): array
    {
        return $this->request->getCookieParams();
    }

    public function withCookieParams(array $cookies): static
    {
        $new = clone $this;
        $new->request = $this->request->withCookieParams($cookies);
        return $new;
    }

    public function getQueryParams(): array
    {
        return $this->request->getQueryParams();
    }

    public function withQueryParams(array $query): static
    {
        $new = clone $this;
        $new->request = $this->request->withQueryParams($query);
        return $new;
    }

    public function getUploadedFiles(): array
    {
        return $this->request->getUploadedFiles();
    }

    public function withUploadedFiles(array $uploadedFiles): static
    {
        $new = clone $this;
        $new->request = $this->request->withUploadedFiles($uploadedFiles);
        return $new;
    }

    public function getParsedBody()
    {
        return $this->request->getParsedBody();
    }

    public function withParsedBody($data): static
    {
        $new = clone $this;
        $new->request = $this->request->withParsedBody($data);
        return $new;
    }

    public function getAttributes(): array
    {
        return $this->request->getAttributes();
    }

    public function getAttribute(string $name, $default = null)
    {
        return $this->request->getAttribute($name, $default);
    }

    public function withAttribute(string $name, $value): static
    {
        $new = clone $this;
        $new->request = $this->request->withAttribute($name, $value);
        return $new;
    }

    public function withoutAttribute(string $name): static
    {
        $new = clone $this;
        $new->request = $this->request->withoutAttribute($name);
        return $new;
    }

    /**
     * Get the underlying PSR-7 request
     */
    public function getPsr7Request(): ServerRequestInterface
    {
        return $this->request;
    }
}
