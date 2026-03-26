<?php

namespace Tests\Unit\Http;

use Core\Http\Request;
use Core\Http\TrustedProxyChecker;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    private function createRequest(
        string $method = 'GET',
        string $uri = '/',
        array $serverParams = [],
        array $queryParams = [],
        $body = null
    ): Request {
        $psr7Request = new ServerRequest($method, $uri, [], $body, '1.1', $serverParams);
        $psr7Request = $psr7Request->withQueryParams($queryParams);
        
        return new Request($psr7Request);
    }

    public function test_only_returns_specified_keys(): void
    {
        $request = $this->createRequest('POST', '/', [], ['name' => 'John'], null);
        $request = $request->withParsedBody(['email' => 'john@example.com', 'age' => 30]);

        $result = $request->only(['email', 'age']);

        $this->assertEquals(['email' => 'john@example.com', 'age' => 30], $result);
    }

    public function test_except_excludes_specified_keys(): void
    {
        $request = $this->createRequest('POST', '/', [], ['name' => 'John'], null);
        $request = $request->withParsedBody(['email' => 'john@example.com', 'password' => 'secret']);

        $result = $request->except(['password']);

        $this->assertEquals(['name' => 'John', 'email' => 'john@example.com'], $result);
    }

    public function test_query_returns_all_query_params_when_no_key(): void
    {
        $request = $this->createRequest('GET', '/', [], ['page' => '1', 'limit' => '10']);

        $result = $request->query();

        $this->assertEquals(['page' => '1', 'limit' => '10'], $result);
    }

    public function test_query_returns_specific_param(): void
    {
        $request = $this->createRequest('GET', '/', [], ['page' => '1', 'limit' => '10']);

        $this->assertEquals('1', $request->query('page'));
        $this->assertEquals('10', $request->query('limit'));
        $this->assertNull($request->query('nonexistent'));
    }

    public function test_post_returns_parsed_body(): void
    {
        $request = $this->createRequest('POST', '/');
        $request = $request->withParsedBody(['name' => 'John', 'email' => 'john@example.com']);

        $this->assertEquals(['name' => 'John', 'email' => 'john@example.com'], $request->post());
        $this->assertEquals('John', $request->post('name'));
        $this->assertNull($request->post('nonexistent'));
    }

    public function test_json_parses_json_body(): void
    {
        $jsonData = ['name' => 'John', 'age' => 30];
        $request = $this->createRequest('POST', '/', [], [], json_encode($jsonData));

        $this->assertEquals($jsonData, $request->json());
        $this->assertEquals('John', $request->json('name'));
        $this->assertEquals(30, $request->json('age'));
    }

    public function test_filled_returns_true_for_non_empty_values(): void
    {
        $request = $this->createRequest('POST', '/');
        $request = $request->withParsedBody(['name' => 'John', 'email' => '', 'age' => 0]);

        $this->assertTrue($request->filled('name'));
        $this->assertFalse($request->filled('email'));
        $this->assertFalse($request->filled('nonexistent'));
    }

    public function test_missing_returns_true_for_absent_keys(): void
    {
        $request = $this->createRequest('POST', '/');
        $request = $request->withParsedBody(['name' => 'John']);

        $this->assertFalse($request->missing('name'));
        $this->assertTrue($request->missing('email'));
    }

    public function test_bearer_token_extracts_token_from_authorization_header(): void
    {
        $request = $this->createRequest('GET', '/');
        $request = $request->withHeader('Authorization', 'Bearer abc123xyz');

        $this->assertEquals('abc123xyz', $request->bearerToken());
    }

    public function test_bearer_token_returns_null_when_no_token(): void
    {
        $request = $this->createRequest('GET', '/');

        $this->assertNull($request->bearerToken());
    }

    public function test_is_json_returns_true_for_json_content_type(): void
    {
        $request = $this->createRequest('POST', '/');
        $request = $request->withHeader('Content-Type', 'application/json');

        $this->assertTrue($request->isJson());
    }

    public function test_wants_json_returns_true_when_accept_header_is_json(): void
    {
        $request = $this->createRequest('GET', '/');
        $request = $request->withHeader('Accept', 'application/json');

        $this->assertTrue($request->wantsJson());
    }

    public function test_ajax_returns_true_for_xmlhttprequest(): void
    {
        $request = $this->createRequest('GET', '/');
        $request = $request->withHeader('X-Requested-With', 'XMLHttpRequest');

        $this->assertTrue($request->ajax());
        $this->assertTrue($request->isXmlHttpRequest());
    }

    public function test_pjax_returns_true_when_pjax_header_present(): void
    {
        $request = $this->createRequest('GET', '/');
        $request = $request->withHeader('X-PJAX', 'true');

        $this->assertTrue($request->pjax());
    }

    public function test_secure_returns_true_for_https(): void
    {
        $request = $this->createRequest('GET', '/', ['HTTPS' => 'on']);

        $this->assertTrue($request->secure());
    }

    public function test_secure_checks_x_forwarded_proto(): void
    {
        $request = $this->createRequest('GET', '/');
        $request = $request->withHeader('X-Forwarded-Proto', 'https');

        $this->assertTrue($request->secure());
    }

    public function test_cookie_returns_cookie_value(): void
    {
        $request = $this->createRequest('GET', '/');
        $request = $request->withCookieParams(['session' => 'abc123', 'user_id' => '42']);

        $this->assertEquals('abc123', $request->cookie('session'));
        $this->assertEquals('42', $request->cookie('user_id'));
        $this->assertNull($request->cookie('nonexistent'));
    }

    public function test_ip_returns_remote_addr_when_no_proxy(): void
    {
        // Create a mock config file temporarily
        $configPath = __DIR__ . '/../../../config/trustedproxy.php';
        $configExists = file_exists($configPath);
        
        if (!$configExists) {
            // Create temporary config
            @mkdir(dirname($configPath), 0755, true);
            file_put_contents($configPath, "<?php\nreturn ['proxies' => null];");
        }

        $request = $this->createRequest('GET', '/', ['REMOTE_ADDR' => '192.168.1.100']);

        $this->assertEquals('192.168.1.100', $request->ip());

        // Cleanup
        if (!$configExists && file_exists($configPath)) {
            @unlink($configPath);
        }
    }

    public function test_format_returns_json_for_json_accept(): void
    {
        $request = $this->createRequest('GET', '/');
        $request = $request->withHeader('Accept', 'application/json');

        $this->assertEquals('json', $request->format());
    }

    public function test_format_returns_html_as_default(): void
    {
        $request = $this->createRequest('GET', '/');
        $request = $request->withHeader('Accept', 'text/html');

        $this->assertEquals('html', $request->format());
    }
}
