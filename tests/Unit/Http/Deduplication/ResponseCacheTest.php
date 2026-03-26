<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Deduplication;

use App\Http\ResponseFactory;
use Core\Application;
use Core\Http\Deduplication\ResponseCache;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Mockery;

class ResponseCacheTest extends TestCase
{
    protected CacheInterface $cache;
    protected ResponseCache $responseCache;
    protected Application $app;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = new Application(dirname(__DIR__, 4));
        Application::setInstance($this->app);
        $this->app->instance(ResponseFactory::class, new ResponseFactory());

        $this->cache = Mockery::mock(CacheInterface::class);
        $this->responseCache = new ResponseCache($this->cache, 10, 'resp:');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_store_response(): void
    {
        $signature = 'test-signature-123';
        $cacheKey = "resp:{$signature}";

        $response = new Response(200, ['Content-Type' => 'application/json'], '{"data":"test"}');

        $this->cache->shouldReceive('set')
            ->with($cacheKey, Mockery::on(function ($data) {
                return is_array($data)
                    && $data['status'] === 200
                    && isset($data['headers'])
                    && isset($data['body'])
                    && isset($data['cached_at']);
            }), 10)
            ->once()
            ->andReturn(true);

        $this->responseCache->store($signature, $response);
        $this->assertTrue(true);
    }

    public function test_store_response_with_custom_ttl(): void
    {
        $signature = 'test-signature-123';
        $cacheKey = "resp:{$signature}";
        $customTtl = 30;
        $responseCache = new ResponseCache($this->cache, $customTtl, 'resp:');

        $response = new Response(200, [], 'test body');

        $this->cache->shouldReceive('set')->with($cacheKey, Mockery::any(), $customTtl)->once()->andReturn(true);

        $responseCache->store($signature, $response);
        $this->assertTrue(true);
    }

    public function test_get_cached_response(): void
    {
        $signature = 'test-signature-123';
        $cacheKey = "resp:{$signature}";

        $cachedData = [
            'status' => 200,
            'headers' => ['Content-Type' => ['application/json']],
            'body' => '{"data":"test"}',
            'cached_at' => time(),
        ];

        $this->cache->shouldReceive('get')->with($cacheKey)->once()->andReturn($cachedData);

        $result = $this->responseCache->get($signature);

        $this->assertNotNull($result);
        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals('{"data":"test"}', (string) $result->getBody());
    }

    public function test_get_returns_null_when_not_cached(): void
    {
        $signature = 'test-signature-123';
        $cacheKey = "resp:{$signature}";

        $this->cache->shouldReceive('get')->with($cacheKey)->once()->andReturn(null);

        $result = $this->responseCache->get($signature);
        $this->assertNull($result);
    }

    public function test_has_returns_true_when_cached(): void
    {
        $signature = 'test-signature-123';
        $cacheKey = "resp:{$signature}";

        $this->cache->shouldReceive('has')->with($cacheKey)->once()->andReturn(true);
        $result = $this->responseCache->has($signature);
        $this->assertTrue($result);
    }

    public function test_has_returns_false_when_not_cached(): void
    {
        $signature = 'test-signature-123';
        $cacheKey = "resp:{$signature}";

        $this->cache->shouldReceive('has')->with($cacheKey)->once()->andReturn(false);
        $result = $this->responseCache->has($signature);
        $this->assertFalse($result);
    }

    public function test_store_does_not_cache_non_2xx(): void
    {
        $signature = 'test-signature-123';
        $response = new Response(404, [], 'Not Found');

        $this->cache->shouldReceive('set')->never();
        $this->responseCache->store($signature, $response);
        $this->assertTrue(true);
    }

    public function test_store_preserves_response_headers(): void
    {
        $signature = 'test-signature-123';
        $cacheKey = "resp:{$signature}";

        $response = new Response(200, [
            'Content-Type' => 'application/json',
            'X-Custom-Header' => 'custom-value',
        ], 'test body');

        $this->cache->shouldReceive('set')
            ->with($cacheKey, Mockery::on(function ($data) {
                return isset($data['headers']['Content-Type'], $data['headers']['X-Custom-Header']);
            }), 10)
            ->once()
            ->andReturn(true);

        $this->responseCache->store($signature, $response);
        $this->assertTrue(true);
    }

    public function test_different_signatures_use_different_cache_keys(): void
    {
        $signature1 = 'signature-1';
        $signature2 = 'signature-2';
        $response = new Response(200, [], 'test');

        $this->cache->shouldReceive('set')->with("resp:{$signature1}", Mockery::any(), Mockery::any())->once()->andReturn(true);
        $this->cache->shouldReceive('set')->with("resp:{$signature2}", Mockery::any(), Mockery::any())->once()->andReturn(true);

        $this->responseCache->store($signature1, $response);
        $this->responseCache->store($signature2, $response);
        $this->assertTrue(true);
    }

    public function test_custom_key_prefix(): void
    {
        $responseCache = new ResponseCache($this->cache, 10, 'dedup:resp:');
        $signature = 'sig';
        $response = new Response(200, [], 'ok');

        $this->cache->shouldReceive('set')->with('dedup:resp:sig', Mockery::any(), 10)->once()->andReturn(true);
        $responseCache->store($signature, $response);
        $this->assertTrue(true);
    }
}
