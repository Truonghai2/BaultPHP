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
        private ServerRequestInterface $request,
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
            (array) $this->getParsedBody(),
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
            (array) $this->getParsedBody(),
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
     * Get a subset of the input data
     */
    public function only(array $keys): array
    {
        $all = $this->all();
        $results = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $all)) {
                $results[$key] = $all[$key];
            }
        }

        return $results;
    }

    /**
     * Get all input except for specified keys
     */
    public function except(array $keys): array
    {
        $all = $this->all();

        foreach ($keys as $key) {
            unset($all[$key]);
        }

        return $all;
    }

    /**
     * Get a query parameter
     */
    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->getQueryParams();
        }

        $params = $this->getQueryParams();
        return $params[$key] ?? $default;
    }

    /**
     * Get a POST parameter
     */
    public function post(?string $key = null, mixed $default = null): mixed
    {
        $body = $this->getParsedBody();
        
        if ($key === null) {
            return is_array($body) ? $body : [];
        }

        return is_array($body) && isset($body[$key]) ? $body[$key] : $default;
    }

    /**
     * Get a value from JSON body
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        $body = $this->getBody()->getContents();
        $this->getBody()->rewind();

        if (empty($body)) {
            return $key === null ? [] : $default;
        }

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $key === null ? [] : $default;
        }

        if ($key === null) {
            return $data;
        }

        return $data[$key] ?? $default;
    }

    /**
     * Check if the input has a value that is not empty
     */
    public function filled(string $key): bool
    {
        $value = $this->input($key);
        
        if (is_null($value)) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return !empty($value);
    }

    /**
     * Check if the input is missing
     */
    public function missing(string $key): bool
    {
        return !$this->has($key);
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
     * Get the bearer token from the request headers
     */
    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }

    /**
     * Determine if the request is sending JSON
     */
    public function isJson(): bool
    {
        return str_contains($this->header('Content-Type', ''), 'application/json');
    }

    /**
     * Determine if the current request probably expects a JSON response
     */
    public function expectsJson(): bool
    {
        return $this->isJson() || $this->wantsJson();
    }

    /**
     * Determine if the current request is asking for JSON
     */
    public function wantsJson(): bool
    {
        $acceptable = $this->getAcceptableContentTypes();
        
        return isset($acceptable[0]) && str_contains($acceptable[0], 'application/json');
    }

    /**
     * Get the data format expected in the response
     */
    public function format(string $default = 'html'): string
    {
        foreach ($this->getAcceptableContentTypes() as $type) {
            if ($format = $this->getFormatFromMimeType($type)) {
                return $format;
            }
        }

        return $default;
    }

    /**
     * Get acceptable content types from Accept header
     */
    protected function getAcceptableContentTypes(): array
    {
        $accept = $this->header('Accept', '*/*');
        
        // Simple parsing - just split by comma and trim
        return array_map('trim', explode(',', $accept));
    }

    /**
     * Get format from MIME type
     */
    protected function getFormatFromMimeType(string $mimeType): ?string
    {
        // Remove quality factor if present (e.g., "application/json;q=0.9" -> "application/json")
        $mimeType = explode(';', $mimeType)[0];

        return match ($mimeType) {
            'application/json', 'text/json' => 'json',
            'application/xml', 'text/xml' => 'xml',
            'text/html' => 'html',
            'text/plain' => 'txt',
            'application/javascript', 'text/javascript' => 'js',
            default => null,
        };
    }

    /**
     * Determine if the request is an AJAX request
     */
    public function ajax(): bool
    {
        return $this->isXmlHttpRequest();
    }

    /**
     * Determine if the request is an XMLHttpRequest
     */
    public function isXmlHttpRequest(): bool
    {
        return $this->header('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * Determine if the request is a PJAX request
     */
    public function pjax(): bool
    {
        return $this->hasHeader('X-PJAX');
    }

    /**
     * Determine if the request is over HTTPS
     */
    public function secure(): bool
    {
        $https = $this->getServerParams()['HTTPS'] ?? null;

        if ($https === 'on' || $https === '1') {
            return true;
        }

        // Check X-Forwarded-Proto header from trusted proxies
        $proto = $this->header('X-Forwarded-Proto');
        
        return $proto === 'https';
    }

    /**
     * Get a cookie value
     */
    public function cookie(string $name, mixed $default = null): mixed
    {
        $cookies = $this->getCookieParams();
        return $cookies[$name] ?? $default;
    }

    /**
     * Get the client's IP address.
     *
     * This method checks for proxy headers from trusted proxies first,
     * then falls back to REMOTE_ADDR.
     *
     * @return string|null
     */
    public function ip(): ?string
    {
        $serverParams = $this->request->getServerParams();
        $remoteAddr = $serverParams['REMOTE_ADDR'] ?? null;

        // If no remote address, return null
        if (!$remoteAddr) {
            return null;
        }

        // Check if we should trust proxy headers
        $trustedProxyConfig = config('trustedproxy', []);
        
        // If no trusted proxies configured, return REMOTE_ADDR directly
        if (empty($trustedProxyConfig['proxies'])) {
            return $remoteAddr;
        }

        $proxyChecker = new TrustedProxyChecker($trustedProxyConfig);

        // Only trust proxy headers if the request comes from a trusted proxy
        if (!$proxyChecker->isTrusted($remoteAddr)) {
            return $remoteAddr;
        }

        // Get headers to check from config
        $headersToCheck = $trustedProxyConfig['headers']['client_ip'] ?? [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'HTTP_FORWARDED',
        ];

        // Check each header in order
        foreach ($headersToCheck as $header) {
            $value = $serverParams[$header] ?? null;
            
            if ($value) {
                // X-Forwarded-For can contain multiple IPs: "client, proxy1, proxy2"
                // We want the first (client) IP
                if ($header === 'HTTP_X_FORWARDED_FOR' && str_contains($value, ',')) {
                    $ips = array_map('trim', explode(',', $value));
                    // Return the first non-trusted IP (the actual client)
                    foreach ($ips as $ip) {
                        if (!$proxyChecker->isTrusted($ip)) {
                            return $ip;
                        }
                    }
                    // If all are trusted, return the first one
                    return $ips[0];
                }
                
                return $value;
            }
        }

        // Fallback to REMOTE_ADDR
        return $remoteAddr;
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
