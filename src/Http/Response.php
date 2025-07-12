<?php

namespace Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;

class Response
{
    protected string $content = '';
    protected int $status = 200;
    protected array $headers = [];

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function setStatus(int $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function header(string $key, string $value): static
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function withHeaders(array $headers): static
    {
        foreach ($headers as $key => $value) {
            $this->header($key, $value);
        }
        return $this;
    }

    public function json(array $data, int $status = 200): static
    {
        return $this
            ->setContent(json_encode($data, JSON_UNESCAPED_UNICODE))
            ->setStatus($status)
            ->header('Content-Type', 'application/json');
    }

    public function redirect(string $url, int $status = 302): void
    {
        header("Location: $url", true, $status);
        exit;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function send(): void
    {
        http_response_code($this->status);

        if (!isset($this->headers['Content-Type'])) {
            header('Content-Type: text/html; charset=utf-8');
        }

        foreach ($this->headers as $key => $value) {
            header("$key: $value");
        }

        echo $this->content;
    }

    /**
     * Chuyển đổi Response này thành một PSR-7 ResponseInterface.
     */
    public function toPsr7(): ResponseInterface
    {
        $psr17Factory = new Psr17Factory();

        $response = $psr17Factory->createResponse($this->status);

        $headers = $this->headers;
        if (!isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'text/html; charset=utf-8';
        }

        foreach ($headers as $key => $value) {
            $response = $response->withHeader($key, $value);
        }

        return $response->withBody($psr17Factory->createStream($this->content));
    }
}
