<?php

namespace Core\Http;

use Illuminate\Contracts\View\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Class Response
 * @package Core\Http
 */
class Response implements ResponseInterface
{
    /**
     * @var int
     */
    protected int $statusCode = 200;

    /**
     * @var string
     */
    protected string $protocolVersion = '1.1';

    /**
     * @var string|null
     */
    protected ?string $reasonPhrase = null;

    /**
     * Map of standard HTTP status codes to reason phrases.
     * @var array<int, string>
     */
    private const REASON_PHRASES = [
        100 => 'Continue', 101 => 'Switching Protocols', 102 => 'Processing', 200 => 'OK', 201 => 'Created', 202 => 'Accepted', 203 => 'Non-Authoritative Information', 204 => 'No Content', 205 => 'Reset Content', 206 => 'Partial Content', 207 => 'Multi-Status', 208 => 'Already Reported', 226 => 'IM Used',
        300 => 'Multiple Choices', 301 => 'Moved Permanently', 302 => 'Found', 303 => 'See Other', 304 => 'Not Modified', 305 => 'Use Proxy', 307 => 'Temporary Redirect', 308 => 'Permanent Redirect',
        400 => 'Bad Request', 401 => 'Unauthorized', 402 => 'Payment Required', 403 => 'Forbidden', 404 => 'Not Found', 405 => 'Method Not Allowed', 406 => 'Not Acceptable', 407 => 'Proxy Authentication Required', 408 => 'Request Timeout', 409 => 'Conflict', 410 => 'Gone', 411 => 'Length Required', 412 => 'Precondition Failed', 413 => 'Payload Too Large', 414 => 'URI Too Long', 415 => 'Unsupported Media Type', 416 => 'Range Not Satisfiable', 417 => 'Expectation Failed', 421 => 'Misdirected Request', 422 => 'Unprocessable Entity', 423 => 'Locked', 424 => 'Failed Dependency', 425 => 'Too Early', 426 => 'Upgrade Required', 428 => 'Precondition Required', 429 => 'Too Many Requests', 431 => 'Request Header Fields Too Large', 451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error', 501 => 'Not Implemented', 502 => 'Bad Gateway', 503 => 'Service Unavailable', 504 => 'Gateway Timeout', 505 => 'HTTP Version Not Supported', 506 => 'Variant Also Negotiates', 507 => 'Insufficient Storage', 508 => 'Loop Detected', 510 => 'Not Extended', 511 => 'Network Authentication Required',
    ];

    /**
     * Headers stored as array<string, array<string>>
     * Each header can have multiple values
     * 
     * @var array<string, array<string>>
     */
    protected array $headers = [];

    /**
     * @var StreamInterface
     */
    protected StreamInterface $body;

    /**
     * Response constructor.
     * @param string|View $content
     * @param int $status
     * @param array $headers
     */
    public function __construct($content = '', int $status = 200, array $headers = [])
    {
        // If the content is a View object, render it to get the HTML string.
        if ($content instanceof View) {
            $content = $content->render();
        }

        $this->statusCode = $status;
        
        // Normalize headers to array format
        $defaultHeaders = ['Content-Type' => ['text/html; charset=UTF-8']];
        $this->headers = $this->normalizeHeaders(array_merge($defaultHeaders, $headers));
        
        // Ensure the content is a string before creating the stream.
        $this->body = new StringStream((string) $content);
    }

    /**
     * Normalize headers to array<string, array<string>> format
     *
     * @param array $headers
     * @return array<string, array<string>>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        
        foreach ($headers as $name => $value) {
            $normalized[$name] = is_array($value) ? $value : [$value];
        }
        
        return $normalized;
    }

    /**
     * Create a JSON response.
     * @param array $data
     * @param int $status
     * @param array $headers
     * @return self
     */
    public static function json(array $data, int $status = 200, array $headers = []): self
    {
        $headers['Content-Type'] = ['application/json'];
        return new self(json_encode($data), $status, $headers);
    }

    /**
     * Create a redirect response.
     * @param string $url
     * @param int $status
     * @param array $headers
     * @return self
     */
    public static function redirect(string $url, int $status = 302, array $headers = []): self
    {
        return new self('', $status, array_merge($headers, ['Location' => $url]));
    }

    /**
     * Get the response status code.
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @param int $code
     * @param string $reasonPhrase
     * @return ResponseInterface
     */
    public function withStatus($code, $reasonPhrase = ''): ResponseInterface
    {
        $new = clone $this;
        $new->statusCode = $code;
        $new->reasonPhrase = $reasonPhrase;
        return $new;
    }

    /**
     * Get the reason phrase associated with the response status code.
     * @return string
     */
    public function getReasonPhrase(): string
    {
        if (!empty($this->reasonPhrase)) {
            return $this->reasonPhrase;
        }
        return self::REASON_PHRASES[$this->statusCode] ?? '';
    }

    /**
     * Get the HTTP protocol version.
     * @return string
     */
    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    /**
     * @param string $version
     * @return ResponseInterface
     */
    public function withProtocolVersion($version): ResponseInterface
    {
        $new = clone $this;
        $new->protocolVersion = $version;
        return $new;
    }

    /**
     * Get the headers as an associative array.
     * Each header name maps to an array of values.
     * 
     * @return array<string, array<string>>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Check if the response has a specific header.
     * Case-insensitive check.
     * 
     * @param string $name
     * @return bool
     */
    public function hasHeader($name): bool
    {
        return $this->findHeaderKey($name) !== null;
    }

    /**
     * Get a specific header value as an array.
     * 
     * @param string $name
     * @return array<string>
     */
    public function getHeader($name): array
    {
        $key = $this->findHeaderKey($name);
        return $key !== null ? $this->headers[$key] : [];
    }

    /**
     * Get a specific header value as a comma-separated string.
     * 
     * @param string $name
     * @return string
     */
    public function getHeaderLine($name): string
    {
        $values = $this->getHeader($name);
        return implode(', ', $values);
    }

    /**
     * Find the actual header key (case-insensitive).
     * 
     * @param string $name
     * @return string|null
     */
    private function findHeaderKey(string $name): ?string
    {
        $lowerName = strtolower($name);
        
        foreach (array_keys($this->headers) as $key) {
            if (strtolower($key) === $lowerName) {
                return $key;
            }
        }
        
        return null;
    }

    /**
     * Replace a header with a new value.
     * 
     * @param string $name
     * @param string|array<string> $value
     * @return ResponseInterface
     */
    public function withHeader($name, $value): ResponseInterface
    {
        $new = clone $this;
        
        // Remove existing header (case-insensitive)
        $existingKey = $new->findHeaderKey($name);
        if ($existingKey !== null) {
            unset($new->headers[$existingKey]);
        }
        
        // Add new header
        $new->headers[$name] = is_array($value) ? $value : [$value];
        
        return $new;
    }

    /**
     * Add a value to an existing header.
     * 
     * @param string $name
     * @param string|array<string> $value
     * @return ResponseInterface
     */
    public function withAddedHeader($name, $value): ResponseInterface
    {
        $new = clone $this;
        
        $existingKey = $new->findHeaderKey($name);
        $values = is_array($value) ? $value : [$value];
        
        if ($existingKey !== null) {
            // Append to existing header
            $new->headers[$existingKey] = array_merge($new->headers[$existingKey], $values);
        } else {
            // Create new header
            $new->headers[$name] = $values;
        }
        
        return $new;
    }

    /**
     * Remove a specific header.
     * 
     * @param string $name
     * @return ResponseInterface
     */
    public function withoutHeader($name): ResponseInterface
    {
        $new = clone $this;
        
        $existingKey = $new->findHeaderKey($name);
        if ($existingKey !== null) {
            unset($new->headers[$existingKey]);
        }
        
        return $new;
    }

    /**
     * Get the response body.
     * @return StreamInterface
     */
    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    /**
     * @param StreamInterface $body
     * @return ResponseInterface
     */
    public function withBody(StreamInterface $body): ResponseInterface
    {
        $new = clone $this;
        $new->body = $body;
        return $new;
    }

    /**
     * Set a cookie on the response.
     *
     * @param string $name
     * @param string $value
     * @param int $minutes
     * @param string $path
     * @param string|null $domain
     * @param bool $secure
     * @param bool $httpOnly
     * @param string $sameSite
     * @return self
     */
    public function cookie(
        string $name,
        string $value,
        int $minutes = 0,
        string $path = '/',
        ?string $domain = null,
        bool $secure = false,
        bool $httpOnly = true,
        string $sameSite = 'Lax'
    ): self {
        $expire = $minutes === 0 ? 0 : time() + ($minutes * 60);
        
        $cookie = sprintf(
            '%s=%s',
            rawurlencode($name),
            rawurlencode($value)
        );

        if ($expire !== 0) {
            $cookie .= '; Expires=' . gmdate('D, d M Y H:i:s T', $expire);
            $cookie .= '; Max-Age=' . ($minutes * 60);
        }

        $cookie .= '; Path=' . $path;

        if ($domain) {
            $cookie .= '; Domain=' . $domain;
        }

        if ($secure) {
            $cookie .= '; Secure';
        }

        if ($httpOnly) {
            $cookie .= '; HttpOnly';
        }

        if ($sameSite) {
            $cookie .= '; SameSite=' . $sameSite;
        }

        return $this->withAddedHeader('Set-Cookie', $cookie);
    }

    /**
     * Remove a cookie from the response.
     *
     * @param string $name
     * @param string $path
     * @param string|null $domain
     * @return self
     */
    public function withoutCookie(string $name, string $path = '/', ?string $domain = null): self
    {
        return $this->cookie($name, '', -2628000, $path, $domain);
    }

    /**
     * Create a file download response.
     *
     * @param string $file Path to file
     * @param string|null $name Download filename (defaults to original filename)
     * @param array $headers Additional headers
     * @return self
     */
    public static function download(string $file, ?string $name = null, array $headers = []): self
    {
        if (!file_exists($file)) {
            throw new \InvalidArgumentException("File not found: {$file}");
        }

        $name = $name ?? basename($file);
        $content = file_get_contents($file);
        
        $defaultHeaders = [
            'Content-Type' => ['application/octet-stream'],
            'Content-Disposition' => ['attachment; filename="' . $name . '"'],
            'Content-Length' => [(string) strlen($content)],
        ];

        return new self($content, 200, array_merge($defaultHeaders, $headers));
    }

    /**
     * Create a file response (inline display).
     *
     * @param string $file Path to file
     * @param array $headers Additional headers
     * @return self
     */
    public static function file(string $file, array $headers = []): self
    {
        if (!file_exists($file)) {
            throw new \InvalidArgumentException("File not found: {$file}");
        }

        $content = file_get_contents($file);
        $mimeType = mime_content_type($file) ?: 'application/octet-stream';
        
        $defaultHeaders = [
            'Content-Type' => [$mimeType],
            'Content-Length' => [(string) strlen($content)],
        ];

        return new self($content, 200, array_merge($defaultHeaders, $headers));
    }

    /**
     * Create a no content response.
     *
     * @param array $headers
     * @return self
     */
    public static function noContent(array $headers = []): self
    {
        return new self('', 204, $headers);
    }

    /**
     * Set the content of the response.
     *
     * @param string $content
     * @return self
     */
    public function setContent(string $content): self
    {
        return $this->withBody(new StringStream($content));
    }
}
