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
     * @var array
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
        // Always specify UTF-8 to prevent character encoding issues.
        $this->headers = array_merge(['Content-Type' => 'text/html; charset=UTF-8'], $headers);
        // Ensure the content is a string before creating the stream.
        $this->body = new StringStream((string) $content);
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
        $headers['Content-Type'] = 'application/json';
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
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Check if the response has a specific header.
     * @param string $name
     * @return bool
     */
    public function hasHeader($name): bool
    {
        return isset($this->headers[$name]);
    }

    /**
     * Get a specific header value.
     * @param string $name
     * @return array
     */
    public function getHeader($name): array
    {
        return isset($this->headers[$name]) ? [$this->headers[$name]] : [];
    }

    /**
     * Get a specific header value.
     * @param string $name
     * @return string
     */
    public function getHeaderLine($name): string
    {
        return $this->headers[$name] ?? '';
    }

    /**
     * @param string $name
     * @param string $value
     * @return ResponseInterface
     */
    public function withHeader($name, $value): ResponseInterface
    {
        $new = clone $this;
        $new->headers[$name] = $value;
        return $new;
    }

    /**
     * @param string $name
     * @param string $value
     * @return ResponseInterface
     */
    public function withAddedHeader($name, $value): ResponseInterface
    {
        $new = clone $this;
        if (isset($new->headers[$name])) {
            $new->headers[$name] = array_merge((array)$new->headers[$name], (array)$value);
        } else {
            $new->headers[$name] = $value;
        }
        return $new;
    }

    /**
     * Remove a specific header.
     * @param string $name
     * @return ResponseInterface
     */
    public function withoutHeader($name): ResponseInterface
    {
        $new = clone $this;
        unset($new->headers[$name]);
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
}
