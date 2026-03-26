<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use Core\Http\RequestBatcher;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

class RequestBatcherTest extends TestCase
{
    protected RequestBatcher $batcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->batcher = new RequestBatcher([]);
    }

    public function test_batch_empty_requests_returns_empty_array(): void
    {
        $results = $this->batcher->batch([]);
        
        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    public function test_batch_sequential_requests(): void
    {
        $requests = [
            fn() => 'result1',
            fn() => 'result2',
            fn() => 'result3',
        ];

        $results = $this->batcher->batch($requests, ['parallel' => false]);

        $this->assertCount(3, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertEquals('result1', $results[0]['result']);
        $this->assertTrue($results[1]['success']);
        $this->assertEquals('result2', $results[1]['result']);
        $this->assertTrue($results[2]['success']);
        $this->assertEquals('result3', $results[2]['result']);
    }

    public function test_batch_handles_exceptions(): void
    {
        $requests = [
            fn() => 'success',
            fn() => throw new \RuntimeException('Test error'),
            fn() => 'another success',
        ];

        $results = $this->batcher->batch($requests, ['parallel' => false]);

        $this->assertCount(3, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertFalse($results[1]['success']);
        $this->assertEquals('Test error', $results[1]['error']);
        $this->assertTrue($results[2]['success']);
    }

    public function test_batch_with_http_requests(): void
    {
        $requests = [
            new ServerRequest('GET', '/api/users'),
            new ServerRequest('POST', '/api/posts'),
        ];

        $results = $this->batcher->batch($requests, ['parallel' => false]);

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertIsArray($results[0]['result']);
        $this->assertEquals('GET', $results[0]['result']['method']);
        $this->assertTrue($results[1]['success']);
        $this->assertEquals('POST', $results[1]['result']['method']);
    }

    public function test_batch_rejects_invalid_request_type(): void
    {
        $requests = ['invalid_request'];

        $results = $this->batcher->batch($requests, ['parallel' => false]);

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]['success']);
        $this->assertEquals('Invalid request type', $results[0]['error']);
    }

    public function test_coalesce_groups_similar_requests(): void
    {
        $request1 = new ServerRequest('GET', '/api/users', [], null, '1.1', [], null, null, ['id' => 1]);
        $request2 = new ServerRequest('GET', '/api/users', [], null, '1.1', [], null, null, ['id' => 1]);
        $request3 = new ServerRequest('GET', '/api/posts');

        $requests = [$request1, $request2, $request3];

        $results = $this->batcher->coalesce($requests);

        $this->assertCount(3, $results);
        // First two requests should have same result (coalesced)
        $this->assertEquals($results[0], $results[1]);
        // Third request should be different
        $this->assertNotEquals($results[0], $results[2]);
    }

    public function test_coalesce_with_custom_key_generator(): void
    {
        $requests = [
            fn() => 'result1',
            fn() => 'result2',
            fn() => 'result3',
        ];

        $keyGenerator = fn($req) => 'same_key';

        $results = $this->batcher->coalesce($requests, $keyGenerator);

        $this->assertCount(3, $results);
        // All should have same result due to same key
        $this->assertEquals($results[0], $results[1]);
        $this->assertEquals($results[1], $results[2]);
    }

    public function test_batch_queries(): void
    {
        $queries = ['SELECT * FROM users', 'SELECT * FROM posts'];
        $executor = fn($query) => "Result for: {$query}";

        $results = $this->batcher->batchQueries($queries, $executor);

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertStringContainsString('users', $results[0]['result']);
        $this->assertTrue($results[1]['success']);
        $this->assertStringContainsString('posts', $results[1]['result']);
    }

    public function test_batch_api_calls(): void
    {
        $urls = ['https://api.example.com/users', 'https://api.example.com/posts'];
        $httpClient = fn($url) => "Response from: {$url}";

        $results = $this->batcher->batchApiCalls($urls, $httpClient);

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertStringContainsString('users', $results[0]['result']);
        $this->assertTrue($results[1]['success']);
        $this->assertStringContainsString('posts', $results[1]['result']);
    }

    public function test_get_stats(): void
    {
        $stats = $this->batcher->getStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('batches_processed', $stats);
        $this->assertArrayHasKey('results_cached', $stats);
        $this->assertIsInt($stats['batches_processed']);
        $this->assertIsInt($stats['results_cached']);
    }

    public function test_batch_with_timeout_option(): void
    {
        $requests = [
            fn() => 'result1',
            fn() => 'result2',
        ];

        $results = $this->batcher->batch($requests, [
            'parallel' => false,
            'timeout' => 10,
        ]);

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertTrue($results[1]['success']);
    }

    public function test_batch_with_max_concurrency_option(): void
    {
        $requests = [
            fn() => 'result1',
            fn() => 'result2',
            fn() => 'result3',
        ];

        $results = $this->batcher->batch($requests, [
            'parallel' => false,
            'max_concurrency' => 2,
        ]);

        $this->assertCount(3, $results);
    }

    public function test_generate_request_key_for_callable(): void
    {
        $callable1 = fn() => 'test';
        $callable2 = fn() => 'test';

        $request1 = new ServerRequest('GET', '/test');
        $request2 = new ServerRequest('GET', '/test');

        // Same HTTP requests should generate same key
        $results1 = $this->batcher->coalesce([$request1, $request2]);
        $this->assertEquals($results1[0], $results1[1]);
    }

    public function test_coalesce_preserves_order(): void
    {
        $requests = [
            fn() => 'first',
            fn() => 'second',
            fn() => 'third',
        ];

        $results = $this->batcher->coalesce($requests);

        $this->assertCount(3, $results);
        // Results should be in same order as input
        $this->assertEquals('first', $results[0]);
        $this->assertEquals('second', $results[1]);
        $this->assertEquals('third', $results[2]);
    }
}
