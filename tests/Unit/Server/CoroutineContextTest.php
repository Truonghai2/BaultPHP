<?php

namespace Tests\Unit\Server;

use Core\Server\CoroutineContext;
use PHPUnit\Framework\TestCase;

class CoroutineContextTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear all contexts before each test
        CoroutineContext::clearAll();
    }

    protected function tearDown(): void
    {
        // Cleanup after each test
        CoroutineContext::clearAll();
        parent::tearDown();
    }

    public function test_set_and_get_value(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        \Swoole\Coroutine\run(function() {
            CoroutineContext::set('test_key', 'test_value');
            
            $value = CoroutineContext::get('test_key');
            
            $this->assertEquals('test_value', $value);
        });
    }

    public function test_get_with_default_value(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        \Swoole\Coroutine\run(function() {
            $value = CoroutineContext::get('nonexistent_key', 'default_value');
            
            $this->assertEquals('default_value', $value);
        });
    }

    public function test_has_returns_true_for_existing_key(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        \Swoole\Coroutine\run(function() {
            CoroutineContext::set('existing_key', 'value');
            
            $this->assertTrue(CoroutineContext::has('existing_key'));
            $this->assertFalse(CoroutineContext::has('nonexistent_key'));
        });
    }

    public function test_delete_removes_key(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        \Swoole\Coroutine\run(function() {
            CoroutineContext::set('key_to_delete', 'value');
            $this->assertTrue(CoroutineContext::has('key_to_delete'));
            
            CoroutineContext::delete('key_to_delete');
            
            $this->assertFalse(CoroutineContext::has('key_to_delete'));
        });
    }

    public function test_all_returns_all_context_data(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        \Swoole\Coroutine\run(function() {
            CoroutineContext::set('key1', 'value1');
            CoroutineContext::set('key2', 'value2');
            
            $all = CoroutineContext::all();
            
            $this->assertEquals([
                'key1' => 'value1',
                'key2' => 'value2',
            ], $all);
        });
    }

    public function test_clear_removes_all_context(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        \Swoole\Coroutine\run(function() {
            CoroutineContext::set('key1', 'value1');
            CoroutineContext::set('key2', 'value2');
            
            CoroutineContext::clear();
            
            $this->assertEmpty(CoroutineContext::all());
        });
    }

    public function test_context_is_isolated_between_coroutines(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        \Swoole\Coroutine\run(function() {
            $results = [];
            
            // Parent coroutine
            CoroutineContext::set('parent_key', 'parent_value');
            
            // Child coroutine 1
            go(function() use (&$results) {
                CoroutineContext::set('child1_key', 'child1_value');
                $results['child1'] = CoroutineContext::all();
            });
            
            // Child coroutine 2
            go(function() use (&$results) {
                CoroutineContext::set('child2_key', 'child2_value');
                $results['child2'] = CoroutineContext::all();
            });
            
            // Wait for children to complete
            \Swoole\Coroutine::sleep(0.01);
            
            // Each coroutine should have isolated context
            $this->assertArrayNotHasKey('child1_key', $results['child2'] ?? []);
            $this->assertArrayNotHasKey('child2_key', $results['child1'] ?? []);
        });
    }

    public function test_copy_duplicates_context(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        \Swoole\Coroutine\run(function() {
            $parentCid = \Swoole\Coroutine::getCid();
            CoroutineContext::set('parent_data', 'test');
            
            go(function() use ($parentCid) {
                $childCid = \Swoole\Coroutine::getCid();
                CoroutineContext::copy($parentCid, $childCid);
                
                // Child should have parent's data
                $this->assertEquals('test', CoroutineContext::get('parent_data'));
            });
            
            \Swoole\Coroutine::sleep(0.01);
        });
    }

    public function test_register_child_copies_parent_context(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        \Swoole\Coroutine\run(function() {
            CoroutineContext::set('shared_data', 'parent_value');
            
            go(function() {
                $childCid = \Swoole\Coroutine::getCid();
                CoroutineContext::registerChild($childCid);
                
                // Child should have parent's data
                $this->assertEquals('parent_value', CoroutineContext::get('shared_data'));
            });
            
            \Swoole\Coroutine::sleep(0.01);
        });
    }

    public function test_get_current_id_returns_coroutine_id(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        \Swoole\Coroutine\run(function() {
            $cid = CoroutineContext::getCurrentId();
            
            $this->assertGreaterThan(0, $cid);
            $this->assertEquals(\Swoole\Coroutine::getCid(), $cid);
        });
    }

    public function test_is_in_coroutine_returns_true_in_coroutine(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        \Swoole\Coroutine\run(function() {
            $this->assertTrue(CoroutineContext::isInCoroutine());
        });
    }

    public function test_get_stats_returns_statistics(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        \Swoole\Coroutine\run(function() {
            CoroutineContext::set('key1', 'value1');
            CoroutineContext::set('key2', 'value2');
            
            $stats = CoroutineContext::getStats();
            
            $this->assertArrayHasKey('active_contexts', $stats);
            $this->assertArrayHasKey('total_memory_usage', $stats);
            $this->assertGreaterThan(0, $stats['active_contexts']);
        });
    }

    public function test_operations_outside_coroutine_are_safe(): void
    {
        if (!extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is not available');
        }

        // These should not throw exceptions when outside coroutine context
        // getCid() will return -1, operations should be safe
        CoroutineContext::set('key', 'value');
        $value = CoroutineContext::get('key', 'default');
        $this->assertEquals('default', $value);
        
        $this->assertFalse(CoroutineContext::has('key'));
        $this->assertEmpty(CoroutineContext::all());
    }
}
