<?php

namespace Tests\Unit\Http;

use Core\Http\Response;
use Core\Http\StringStream;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    public function test_response_stores_multiple_header_values(): void
    {
        $response = new Response('', 200, []);
        $response = $response->withHeader('Set-Cookie', 'session=abc123');
        $response = $response->withAddedHeader('Set-Cookie', 'user=john');

        $cookies = $response->getHeader('Set-Cookie');

        $this->assertCount(2, $cookies);
        $this->assertContains('session=abc123', $cookies);
        $this->assertContains('user=john', $cookies);
    }

    public function test_get_header_line_joins_multiple_values(): void
    {
        $response = new Response('', 200, []);
        $response = $response->withHeader('Cache-Control', ['no-cache', 'no-store']);

        $this->assertEquals('no-cache, no-store', $response->getHeaderLine('Cache-Control'));
    }

    public function test_has_header_is_case_insensitive(): void
    {
        $response = new Response('', 200, ['Content-Type' => ['text/html']]);

        $this->assertTrue($response->hasHeader('Content-Type'));
        $this->assertTrue($response->hasHeader('content-type'));
        $this->assertTrue($response->hasHeader('CONTENT-TYPE'));
    }

    public function test_with_header_replaces_existing_header(): void
    {
        $response = new Response('', 200, ['X-Custom' => ['old-value']]);
        $response = $response->withHeader('X-Custom', 'new-value');

        $this->assertEquals(['new-value'], $response->getHeader('X-Custom'));
    }

    public function test_without_header_removes_header(): void
    {
        $response = new Response('', 200, ['X-Custom' => ['value']]);
        $response = $response->withoutHeader('X-Custom');

        $this->assertFalse($response->hasHeader('X-Custom'));
    }

    public function test_json_creates_json_response(): void
    {
        $data = ['name' => 'John', 'age' => 30];
        $response = Response::json($data);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertEquals(json_encode($data), (string) $response->getBody());
    }

    public function test_cookie_adds_set_cookie_header(): void
    {
        $response = new Response();
        $response = $response->cookie('session', 'abc123', 60);

        $cookies = $response->getHeader('Set-Cookie');

        $this->assertCount(1, $cookies);
        $this->assertStringContainsString('session=abc123', $cookies[0]);
        $this->assertStringContainsString('Path=/', $cookies[0]);
        $this->assertStringContainsString('HttpOnly', $cookies[0]);
    }

    public function test_cookie_supports_multiple_cookies(): void
    {
        $response = new Response();
        $response = $response->cookie('session', 'abc123')
                             ->cookie('user_id', '42');

        $cookies = $response->getHeader('Set-Cookie');

        $this->assertCount(2, $cookies);
    }

    public function test_without_cookie_expires_cookie(): void
    {
        $response = new Response();
        $response = $response->withoutCookie('session');

        $cookies = $response->getHeader('Set-Cookie');

        $this->assertCount(1, $cookies);
        $this->assertStringContainsString('session=', $cookies[0]);
        // Should have a past expiration date
        $this->assertStringContainsString('Max-Age=-', $cookies[0]);
    }

    public function test_download_creates_download_response(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'test content');

        $response = Response::download($tempFile, 'download.txt');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/octet-stream', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('attachment', $response->getHeaderLine('Content-Disposition'));
        $this->assertStringContainsString('download.txt', $response->getHeaderLine('Content-Disposition'));
        $this->assertEquals('test content', (string) $response->getBody());

        unlink($tempFile);
    }

    public function test_file_creates_inline_file_response(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'test content');

        $response = Response::file($tempFile);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getHeaderLine('Content-Type'));
        $this->assertEquals('test content', (string) $response->getBody());

        unlink($tempFile);
    }

    public function test_no_content_creates_204_response(): void
    {
        $response = Response::noContent();

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEquals('', (string) $response->getBody());
    }

    public function test_set_content_updates_body(): void
    {
        $response = new Response('old content');
        $response = $response->setContent('new content');

        $this->assertEquals('new content', (string) $response->getBody());
    }

    public function test_with_status_updates_status_code(): void
    {
        $response = new Response();
        $response = $response->withStatus(404, 'Not Found');

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Not Found', $response->getReasonPhrase());
    }

    public function test_redirect_creates_redirect_response(): void
    {
        $response = Response::redirect('https://example.com', 302);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('https://example.com', $response->getHeaderLine('Location'));
    }
}
