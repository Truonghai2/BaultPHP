<?php

namespace Tests;

use Core\Application;
use Psr\Http\Message\ResponseInterface;

/**
 * Test response wrapper that provides convenient assertion methods.
 */
class TestResponse
{
    public function __construct(
        protected ResponseInterface $response,
        protected Application $app,
    ) {
    }

    /**
     * Assert that the response has the given status code.
     */
    public function assertStatus(int $expectedStatus): self
    {
        $actualStatus = $this->response->getStatusCode();
        \PHPUnit\Framework\TestCase::assertEquals(
            $expectedStatus,
            $actualStatus,
            "Expected status code {$expectedStatus} but received {$actualStatus}."
        );
        return $this;
    }

    /**
     * Assert that the response is a redirect to the given URI.
     */
    public function assertRedirect(string $uri = null): self
    {
        $status = $this->response->getStatusCode();
        \PHPUnit\Framework\TestCase::assertTrue(
            $status >= 300 && $status < 400,
            "Response status is not a redirect. Status: {$status}"
        );

        if ($uri !== null) {
            $location = $this->response->getHeaderLine('Location');
            \PHPUnit\Framework\TestCase::assertStringContainsString(
                $uri,
                $location,
                "Expected redirect to '{$uri}' but got '{$location}'."
            );
        }

        return $this;
    }

    /**
     * Assert that the response contains the given string.
     */
    public function assertSee(string $text): self
    {
        $content = (string) $this->response->getBody();
        \PHPUnit\Framework\TestCase::assertStringContainsString(
            $text,
            $content,
            "Response does not contain '{$text}'."
        );
        return $this;
    }

    /**
     * Assert that the response is JSON and matches the given structure.
     */
    public function assertJsonStructure(array $structure, array $data = null): self
    {
        $json = $this->json();
        \PHPUnit\Framework\TestCase::assertIsArray($json, "Response is not valid JSON.");

        $this->assertJsonStructureRecursive($structure, $json);

        return $this;
    }

    /**
     * Recursively check JSON structure.
     */
    protected function assertJsonStructureRecursive(array $structure, array $data): void
    {
        foreach ($structure as $key => $value) {
            if (is_array($value) && isset($value[0])) {
                // Array of items
                \PHPUnit\Framework\TestCase::assertIsArray($data[$key] ?? null);
                if (!empty($data[$key])) {
                    $this->assertJsonStructureRecursive($value[0], $data[$key][0]);
                }
            } elseif (is_array($value)) {
                // Nested structure
                \PHPUnit\Framework\TestCase::assertArrayHasKey($key, $data);
                $this->assertJsonStructureRecursive($value, $data[$key]);
            } else {
                // Simple key
                \PHPUnit\Framework\TestCase::assertArrayHasKey($value, $data);
            }
        }
    }

    /**
     * Assert that the response is JSON and matches the given data.
     */
    public function assertJson(array $data, bool $strict = false): self
    {
        $json = $this->json();
        \PHPUnit\Framework\TestCase::assertIsArray($json, "Response is not valid JSON.");

        if ($strict) {
            \PHPUnit\Framework\TestCase::assertEquals($data, $json);
        } else {
            foreach ($data as $key => $value) {
                \PHPUnit\Framework\TestCase::assertArrayHasKey($key, $json);
                \PHPUnit\Framework\TestCase::assertEquals($value, $json[$key]);
            }
        }

        return $this;
    }

    /**
     * Get the JSON decoded body of the response.
     */
    public function json(string $key = null): mixed
    {
        $content = (string) $this->response->getBody();
        $json = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Response is not valid JSON: ' . json_last_error_msg());
        }

        if ($key === null) {
            return $json;
        }

        return $json[$key] ?? null;
    }

    /**
     * Get the underlying PSR-7 response.
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    /**
     * Get response headers (for compatibility with Laravel-style assertions).
     */
    public function getHeaders(): array
    {
        return $this->response->getHeaders();
    }

    /**
     * Get a specific header line.
     */
    public function getHeaderLine(string $name): string
    {
        return $this->response->getHeaderLine($name);
    }

    /**
     * Magic method to access response properties (for compatibility).
     */
    public function __get(string $name): mixed
    {
        if ($name === 'headers') {
            return new class($this->response) {
                public function __construct(private ResponseInterface $response) {}
                public function get(string $name): ?string
                {
                    return $this->response->getHeaderLine($name) ?: null;
                }
            };
        }

        if ($name === 'status') {
            return $this->response->getStatusCode();
        }

        return null;
    }
}

