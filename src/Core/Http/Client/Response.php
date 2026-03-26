<?php

namespace Core\Http\Client;

use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

/**
 * HTTP Response Wrapper.
 * 
 * Provides convenient methods for working with HTTP responses.
 */
class Response
{
    public function __construct(
        private PsrResponseInterface $response
    ) {
    }

    /**
     * Get response body as string.
     */
    public function body(): string
    {
        return (string) $this->response->getBody();
    }

    /**
     * Get response body as JSON.
     */
    public function json(?string $key = null): mixed
    {
        $data = json_decode($this->body(), true);

        if ($key !== null) {
            return data_get($data, $key);
        }

        return $data;
    }

    /**
     * Get response body as object.
     */
    public function object(?string $key = null): mixed
    {
        $data = json_decode($this->body());

        if ($key !== null) {
            return data_get($data, $key);
        }

        return $data;
    }

    /**
     * Get response body as array.
     */
    public function array(): array
    {
        return $this->json() ?? [];
    }

    /**
     * Get status code.
     */
    public function status(): int
    {
        return $this->response->getStatusCode();
    }

    /**
     * Check if successful (2xx).
     */
    public function successful(): bool
    {
        return $this->status() >= 200 && $this->status() < 300;
    }

    /**
     * Check if OK (200).
     */
    public function ok(): bool
    {
        return $this->status() === 200;
    }

    /**
     * Check if redirect (3xx).
     */
    public function redirect(): bool
    {
        return $this->status() >= 300 && $this->status() < 400;
    }

    /**
     * Check if client error (4xx).
     */
    public function clientError(): bool
    {
        return $this->status() >= 400 && $this->status() < 500;
    }

    /**
     * Check if server error (5xx).
     */
    public function serverError(): bool
    {
        return $this->status() >= 500;
    }

    /**
     * Check if failed (4xx or 5xx).
     */
    public function failed(): bool
    {
        return $this->serverError() || $this->clientError();
    }

    /**
     * Get header value.
     */
    public function header(string $name): ?string
    {
        $values = $this->response->getHeader($name);
        return $values[0] ?? null;
    }

    /**
     * Get all headers.
     */
    public function headers(): array
    {
        return $this->response->getHeaders();
    }

    /**
     * Get cookie value.
     */
    public function cookie(string $name): ?string
    {
        $setCookie = $this->header('Set-Cookie');
        
        if (!$setCookie) {
            return null;
        }

        // Simple cookie parsing
        foreach (explode(';', $setCookie) as $part) {
            $part = trim($part);
            if (str_starts_with($part, $name . '=')) {
                return substr($part, strlen($name) + 1);
            }
        }

        return null;
    }

    /**
     * Throw exception if failed.
     */
    public function throw(): static
    {
        if ($this->failed()) {
            throw new HttpException(
                "HTTP request failed with status {$this->status()}",
                $this->status()
            );
        }

        return $this;
    }

    /**
     * Throw exception if not successful.
     */
    public function throwIf(bool $condition): static
    {
        if ($condition) {
            return $this->throw();
        }

        return $this;
    }

    /**
     * Get PSR-7 response.
     */
    public function toPsrResponse(): PsrResponseInterface
    {
        return $this->response;
    }

    /**
     * Convert to string.
     */
    public function __toString(): string
    {
        return $this->body();
    }
}
