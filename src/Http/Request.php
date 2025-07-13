<?php

namespace Http;

use Core\Routing\Route;
use Psr\Http\Message\ServerRequestInterface;

class Request
{
    /**
     * The route instance for the current request.
     *
     * @var \Core\Routing\Route|null
     */
    public ?Route $route = null;

    protected array $get;
    protected array $post;
    protected array $server;
    protected array $headers;
    protected array $files;
    protected ?string $content;
    protected ?array $json = null;
    protected array $attributes = [];

    public function __construct(
        array $get = [],
        array $post = [],
        array $server = [],
        array $headers = [],
        array $files = [],
        ?string $content = null
    ) {
        $this->get = $get;
        $this->post = $post;
        $this->server = $server;
        $this->headers = $headers;
        $this->files = $files;
        $this->content = $content;
    }

    /**
     * Tạo một Request từ các biến global của PHP (dùng cho FPM).
     */
    public static function capture(): self
    {
        return new self(
            $_GET,
            $_POST,
            $_SERVER,
            function_exists('getallheaders') ? getallheaders() : [],
            $_FILES,
            file_get_contents('php://input')
        );
    }

    /**
     * Tạo một Request từ một PSR-7 ServerRequestInterface (dùng cho RoadRunner).
     */
    public static function fromPsr7(ServerRequestInterface $psrRequest): self
    {
        $headers = [];
        foreach ($psrRequest->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }

        $post = [];
        if (
            str_contains($psrRequest->getHeaderLine('Content-Type'), 'application/x-www-form-urlencoded') ||
            str_contains($psrRequest->getHeaderLine('Content-Type'), 'multipart/form-data')
        ) {
            $post = $psrRequest->getParsedBody() ?? [];
        }

        return new self(
            $psrRequest->getQueryParams(),
            is_array($post) ? $post : [],
            $psrRequest->getServerParams(),
            $headers,
            $psrRequest->getUploadedFiles(), // PSR-7 files are objects, handle accordingly
            (string) $psrRequest->getBody()
        );
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization');
        if ($header && preg_match('/Bearer\s(\S+)/', $header, $matches)) {
            return $matches[1];
        }
        return null;
    }

    public function has(string $key): bool
    {
        return isset($this->post[$key]) || isset($this->get[$key]) || isset($this->json()[$key]);
    }

    public function hasFile(string $key): bool
    {
        return isset($this->files[$key]) && is_uploaded_file($this->files[$key]['tmp_name']);
    }

    public function inputDot(string $key, $default = null)
    {
        $segments = explode('.', $key);
        $value = $this->all();

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function uri(): string
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }

    public function path(): string
    {
        $uri = parse_url($this->uri(), PHP_URL_PATH);
        return rtrim($uri, '/') ?: '/';
    }

    public function input(string $key, $default = null)
    {
        return $this->post[$key] ?? $this->get[$key] ?? $this->json($key, $default);
    }

    public function all(): array
    {
        return array_merge($this->get, $this->post, $this->json() ?? []);
    }

    public function header(string $key, $default = null): ?string
    {
        return $this->headers[$key] ?? $default;
    }

    public function isJson(): bool
    {
        return strpos(strtolower($this->header('Content-Type', '')), 'application/json') !== false;
    }

    public function json(string $key = null, $default = null)
    {
        if ($this->json === null) {
            $this->json = $this->isJson() && $this->content
                ? (json_decode($this->content, true) ?? [])
                : [];
        }

        if ($key === null) return $this->json;

        return $this->json[$key] ?? $default;
    }

    public function query(string $key, $default = null)
    {
        return $this->get[$key] ?? $default;
    }

    // public function post(string $key, $default = null)
    // {
    //     return $this->post[$key] ?? $default;
    // }

    public function file(string $key)
    {
        return $this->files[$key] ?? null;
    }

    public function host(): ?string
    {
        return $this->server['HTTP_HOST'] ?? null;
    }

    public function ip(): ?string
    {
        return $this->server['REMOTE_ADDR'] ?? null;
    }

    public function isAjax(): bool
    {
        return strtolower($this->header('X-Requested-With') ?? '') === 'xmlhttprequest';
    }

    public function scheme(): string
    {
        return (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off') ? 'https' : 'http';
    }

    public function fullUrl(): string
    {
        return $this->scheme() . '://' . $this->host() . $this->uri();
    }

    public function userAgent(): ?string
    {
        return $this->server['HTTP_USER_AGENT'] ?? null;
    }

    public function getAttribute(string $key, $default = null)
    {
        return $this->attributes[$key] ?? $default;
    }

    public function setAttribute(string $key, $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function allAttributes(): array
    {
        return $this->attributes;
    }

    public function get(): array { return $this->get; }
    public function post(): array { return $this->post; }
    public function getServer(): array { return $this->server; }
    public function getHeaders(): array { return $this->headers; }
    public function getFiles(): array { return $this->files; }
    public function getContent(): ?string { return $this->content; }

    public function merge(array $data): void
    {
        if (isset($data['get'])) {
            $this->get = $data['get'];
        }
        if (isset($data['post'])) {
            $this->post = $data['post'];
        }
    }

    public function setJson(array $data): void
    {
        $this->json = $data;
    }

    /**
     * Get a route parameter.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function route(string $key, $default = null)
    {
        return $this->route?->parameters[$key] ?? $default;
    }
}