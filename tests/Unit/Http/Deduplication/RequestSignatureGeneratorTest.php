<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Deduplication;

use Core\Http\Deduplication\RequestSignatureGenerator;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

class RequestSignatureGeneratorTest extends TestCase
{
    protected RequestSignatureGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new RequestSignatureGenerator();
    }

    public function test_generate_signature_for_get_request(): void
    {
        $request = new ServerRequest('GET', '/api/users');

        $signature = $this->generator->generate($request);

        $this->assertIsString($signature);
        $this->assertStringStartsWith('req:', $signature);
        $this->assertGreaterThan(10, strlen($signature));
    }

    public function test_same_requests_generate_same_signature(): void
    {
        $request1 = new ServerRequest('GET', '/api/users');
        $request2 = new ServerRequest('GET', '/api/users');

        $signature1 = $this->generator->generate($request1);
        $signature2 = $this->generator->generate($request2);

        $this->assertEquals($signature1, $signature2);
    }

    public function test_different_methods_generate_different_signatures(): void
    {
        $request1 = new ServerRequest('GET', '/api/users');
        $request2 = new ServerRequest('POST', '/api/users');

        $signature1 = $this->generator->generate($request1);
        $signature2 = $this->generator->generate($request2);

        $this->assertNotEquals($signature1, $signature2);
    }

    public function test_different_paths_generate_different_signatures(): void
    {
        $request1 = new ServerRequest('GET', '/api/users');
        $request2 = new ServerRequest('GET', '/api/posts');

        $signature1 = $this->generator->generate($request1);
        $signature2 = $this->generator->generate($request2);

        $this->assertNotEquals($signature1, $signature2);
    }

    public function test_query_parameters_affect_signature(): void
    {
        $request1 = new ServerRequest('GET', '/api/users?page=1');
        $request2 = new ServerRequest('GET', '/api/users?page=2');

        $signature1 = $this->generator->generate($request1);
        $signature2 = $this->generator->generate($request2);

        $this->assertNotEquals($signature1, $signature2);
    }

    public function test_query_parameters_order_does_not_affect_signature(): void
    {
        $request1 = new ServerRequest('GET', '/api/users?page=1&limit=10');
        $request2 = new ServerRequest('GET', '/api/users?limit=10&page=1');

        $signature1 = $this->generator->generate($request1);
        $signature2 = $this->generator->generate($request2);

        $this->assertEquals($signature1, $signature2);
    }

    public function test_post_request_includes_body_in_signature(): void
    {
        $body1 = json_encode(['name' => 'John', 'email' => 'john@example.com']);
        $body2 = json_encode(['name' => 'Jane', 'email' => 'jane@example.com']);

        $request1 = new ServerRequest('POST', '/api/users', [], $body1);
        $request2 = new ServerRequest('POST', '/api/users', [], $body2);

        $signature1 = $this->generator->generate($request1);
        $signature2 = $this->generator->generate($request2);

        $this->assertNotEquals($signature1, $signature2);
    }

    public function test_same_body_gives_same_signature(): void
    {
        $body = json_encode(['name' => 'John', 'email' => 'john@example.com']);
        $request1 = new ServerRequest('POST', '/api/users', [], $body);
        $request2 = new ServerRequest('POST', '/api/users', [], $body);

        $signature1 = $this->generator->generate($request1);
        $signature2 = $this->generator->generate($request2);
        $this->assertEquals($signature1, $signature2);
    }

    public function test_include_user_id_in_signature(): void
    {
        $user = new class {
            public function getId(): int {
                return 123;
            }
        };

        $request1 = new ServerRequest('GET', '/api/users');
        $request1 = $request1->withAttribute('user', $user);

        $request2 = new ServerRequest('GET', '/api/users');
        $request2 = $request2->withAttribute('user', $user);

        $signature1 = $this->generator->generate($request1, ['include_user' => true]);
        $signature2 = $this->generator->generate($request2, ['include_user' => true]);
        $this->assertEquals($signature1, $signature2);

        $signature3 = $this->generator->generate($request1, ['include_user' => false]);
        $signature4 = $this->generator->generate($request2, ['include_user' => false]);
        $this->assertEquals($signature3, $signature4);
    }

    public function test_different_users_generate_different_signatures(): void
    {
        $user1 = new class {
            public function getId(): int {
                return 123;
            }
        };
        $user2 = new class {
            public function getId(): int {
                return 456;
            }
        };

        $request1 = new ServerRequest('GET', '/api/users');
        $request1 = $request1->withAttribute('user', $user1);
        $request2 = new ServerRequest('GET', '/api/users');
        $request2 = $request2->withAttribute('user', $user2);

        $signature1 = $this->generator->generate($request1, ['include_user' => true]);
        $signature2 = $this->generator->generate($request2, ['include_user' => true]);
        $this->assertNotEquals($signature1, $signature2);
    }

    public function test_include_specific_headers(): void
    {
        $request1 = new ServerRequest('GET', '/api/users', ['X-Custom-Header' => 'value1']);
        $request2 = new ServerRequest('GET', '/api/users', ['X-Custom-Header' => 'value2']);

        $signature1 = $this->generator->generate($request1, [
            'include_headers' => ['X-Custom-Header']
        ]);
        $signature2 = $this->generator->generate($request2, [
            'include_headers' => ['X-Custom-Header']
        ]);

        $this->assertNotEquals($signature1, $signature2);
    }

    public function test_ignored_headers_do_not_affect_signature(): void
    {
        $request1 = new ServerRequest('GET', '/api/users', [
            'User-Agent' => 'Mozilla/5.0',
            'Accept' => 'application/json',
        ]);
        $request2 = new ServerRequest('GET', '/api/users', [
            'User-Agent' => 'Chrome/91.0',
            'Accept' => 'text/html',
        ]);

        $signature1 = $this->generator->generate($request1);
        $signature2 = $this->generator->generate($request2);

        // Should be same because User-Agent and Accept are ignored by default
        $this->assertEquals($signature1, $signature2);
    }

    public function test_user_with_id_property(): void
    {
        $user = new class {
            public $id = 789;
        };
        $request = new ServerRequest('GET', '/api/users');
        $request = $request->withAttribute('user', $user);

        $signature = $this->generator->generate($request, ['include_user' => true]);
        $this->assertIsString($signature);
        $this->assertStringStartsWith('req:', $signature);
    }

    public function test_empty_query_string(): void
    {
        $request1 = new ServerRequest('GET', '/api/users');
        $request2 = new ServerRequest('GET', '/api/users?');

        $signature1 = $this->generator->generate($request1);
        $signature2 = $this->generator->generate($request2);

        $this->assertEquals($signature1, $signature2);
    }

    public function test_put_request_includes_body(): void
    {
        $body = json_encode(['title' => 'Test']);
        $request = new ServerRequest('PUT', '/api/posts/1', [], $body);

        $signature = $this->generator->generate($request);

        $this->assertIsString($signature);
        $this->assertStringStartsWith('req:', $signature);
    }

    public function test_patch_request_includes_body(): void
    {
        $body = json_encode(['title' => 'Updated']);
        $request = new ServerRequest('PATCH', '/api/posts/1', [], $body);

        $signature = $this->generator->generate($request);

        $this->assertIsString($signature);
        $this->assertStringStartsWith('req:', $signature);
    }

    public function test_non_json_body_is_included_as_is(): void
    {
        $body1 = 'name=John&email=john@example.com';
        $body2 = 'name=Jane&email=jane@example.com';

        $request1 = new ServerRequest('POST', '/api/users', [], $body1);
        $request2 = new ServerRequest('POST', '/api/users', [], $body2);

        $signature1 = $this->generator->generate($request1);
        $signature2 = $this->generator->generate($request2);

        $this->assertNotEquals($signature1, $signature2);
    }
}
