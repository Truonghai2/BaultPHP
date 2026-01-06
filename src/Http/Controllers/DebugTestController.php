<?php

namespace App\Http\Controllers;

use Core\Events\ModuleChanged;
use Core\Routing\Attributes\Route;
use Modules\User\Infrastructure\Models\User;

/**
 * Controller để test Debug Bar realtime updates
 *
 * Tất cả endpoints này trigger different operations để test debug bar
 */
class DebugTestController
{
    /**
     * Dashboard: Liệt kê tất cả test endpoints
     */
    #[Route(uri: '/debug-test', method: 'GET', group: 'web')]
    public function index()
    {
        return response()->json([
            'message' => '🧪 Debug Bar Test Suite',
            'available_tests' => [
                '/debug-test/query' => 'Test database queries',
                '/debug-test/event' => 'Test event dispatching',
                '/debug-test/cache' => 'Test cache operations',
                '/debug-test/session' => 'Test session operations',
                '/debug-test/all' => 'Test all operations together',
                '/debug-test/slow-query' => 'Test slow query detection',
            ],
            'instructions' => [
                '1. Make sure APP_DEBUG=true in .env',
                '2. Restart server: docker-compose restart',
                '3. Visit any test route above',
                '4. Watch debug bar at bottom of page',
                '5. Metrics should update in realtime!',
            ],
            'websocket_check' => [
                'Open browser DevTools → Console',
                'Look for: "WebSocket connected"',
                'Look for: "BaultDebugBar: Initializing"',
            ],
        ]);
    }

    /**
     * Test 1: Database Queries
     * Triggers multiple database queries để test query tracking
     */
    #[Route(uri: '/debug-test/query', method: 'GET', group: 'web')]
    public function testQuery()
    {
        // Trigger multiple queries
        $userCount = User::count();
        $users = User::limit(5)->get();
        $firstUser = User::find(1);

        return response()->json([
            'message' => '✅ Query test completed!',
            'queries_executed' => 3,
            'user_count' => $userCount,
            'users_loaded' => $users->count(),
            'first_user_exists' => $firstUser !== null,
            'instruction' => 'Check debug bar - Queries count should show 3 queries!',
            'debug_tip' => 'Click on "Queries" to see SQL details, execution time, and bindings',
        ]);
    }

    /**
     * Test 2: Events
     * Dispatches test events để test event tracking
     */
    #[Route(uri: '/debug-test/event', method: 'GET', group: 'web')]
    public function testEvent()
    {
        // Dispatch test events
        event(new ModuleChanged('DebugTestModule1'));
        event(new ModuleChanged('DebugTestModule2'));

        return response()->json([
            'message' => '✅ Events dispatched!',
            'events_dispatched' => 2,
            'event_class' => ModuleChanged::class,
            'instruction' => 'Check debug bar - Events count should be +2!',
            'debug_tip' => 'Click on "Events" to see event names, payloads, and timestamps',
        ]);
    }

    /**
     * Test 3: Cache
     * Thực hiện cache operations để test cache tracking
     */
    #[Route(uri: '/debug-test/cache', method: 'GET', group: 'web')]
    public function testCache()
    {
        $key = 'debug_test_' . time();

        // Write to cache
        cache()->put($key, [
            'value' => 'test_value_' . rand(1000, 9999),
            'timestamp' => time(),
        ], 60);

        // Read from cache (hit)
        $value = cache()->get($key);

        // Try to get non-existent key (miss)
        $missing = cache()->get('non_existent_key_' . time());

        // Another hit
        cache()->get($key);

        return response()->json([
            'message' => '✅ Cache test completed!',
            'operations' => [
                'writes' => 1,
                'hits' => 2,
                'misses' => 1,
            ],
            'cache_key' => $key,
            'cached_value' => $value,
            'cache_miss' => $missing === null ? 'yes' : 'no',
            'instruction' => 'Check debug bar - Cache metrics should show hits & misses!',
            'debug_tip' => 'Click on "Cache" to see operation types, keys, and stores',
        ]);
    }

    /**
     * Test 4: Session
     * Thực hiện session operations để test session tracking
     */
    #[Route(uri: '/debug-test/session', method: 'GET', group: 'web')]
    public function testSession()
    {
        // Session operations
        session()->set('debug_test_key', 'value_' . time());
        session()->set('debug_test_array', [
            'foo' => 'bar',
            'number' => rand(1, 100),
            'nested' => [
                'level1' => 'value1',
                'level2' => 'value2',
            ],
        ]);

        // Read operations
        $value = session()->get('debug_test_key');
        $array = session()->get('debug_test_array');
        $all = session()->all();

        return response()->json([
            'message' => '✅ Session test completed!',
            'operations' => [
                'writes' => 2,
                'reads' => 3,
            ],
            'session_count' => count($all),
            'test_value' => $value,
            'test_array' => $array,
            'instruction' => 'Check debug bar - Session count should have increased!',
            'debug_tip' => 'Click on "Session" to see all session keys and values',
        ]);
    }

    /**
     * Test 5: All Operations
     * Combines tất cả operations để test tổng hợp
     */
    #[Route(uri: '/debug-test/all', method: 'GET', group: 'web')]
    public function testAll()
    {
        // 1. Query operations
        $userCount = User::count();
        $users = User::limit(3)->get();

        // 2. Event operations
        event(new ModuleChanged('AllTestModule'));

        // 3. Cache operations
        cache()->put('test_all_key', 'combined_value', 60);
        cache()->get('test_all_key');
        cache()->get('non_existent');

        // 4. Session operations
        session()->set('test_all', [
            'executed' => true,
            'timestamp' => time(),
        ]);
        session()->get('test_all');

        return response()->json([
            'message' => '✅ All operations completed!',
            'summary' => [
                'queries' => '2 queries executed',
                'events' => '1 event dispatched',
                'cache' => '1 write, 2 reads (1 hit, 1 miss)',
                'session' => '1 write, 1 read',
            ],
            'details' => [
                'user_count' => $userCount,
                'users_loaded' => $users->count(),
            ],
            'instruction' => 'Check debug bar - ALL metrics should have updated!',
            'debug_tip' => 'This is the best way to see all collectors working together',
        ]);
    }

    /**
     * Test 6: Slow Query
     * Simulates slow query để test performance tracking
     */
    #[Route(uri: '/debug-test/slow-query', method: 'GET', group: 'web')]
    public function testSlowQuery()
    {
        $start = microtime(true);

        // Simulate slow query với SLEEP
        try {
            $result = \Core\Database\Swoole\SwoolePdoPool::withConnection('mysql', function ($pdo) {
                $stmt = $pdo->prepare('SELECT SLEEP(0.5) as slow, NOW() as current_time');
                $stmt->execute();
                return $stmt->fetch(\PDO::FETCH_ASSOC);
            });

            $duration = microtime(true) - $start;

            return response()->json([
                'message' => '✅ Slow query completed!',
                'query_result' => $result,
                'actual_duration_ms' => round($duration * 1000, 2),
                'expected_duration' => '~500ms',
                'instruction' => 'Check debug bar - Query should show ~500ms execution time!',
                'debug_tip' => 'Debug bar highlights slow queries in red/orange for easy identification',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '❌ Slow query test failed',
                'error' => $e->getMessage(),
                'note' => 'Database might not support SLEEP() function',
            ], 500);
        }
    }

    /**
     * Test 7: Multiple Queries
     * Executes nhiều queries để stress test debug bar
     */
    #[Route(uri: '/debug-test/stress', method: 'GET', group: 'web')]
    public function testStress()
    {
        $operations = [];

        // 10 queries
        for ($i = 1; $i <= 10; $i++) {
            User::where('id', $i)->first();
            $operations['queries'][] = "Query {$i}";
        }

        // 5 events
        for ($i = 1; $i <= 5; $i++) {
            event(new ModuleChanged("StressTest{$i}"));
            $operations['events'][] = "Event {$i}";
        }

        // 10 cache operations
        for ($i = 1; $i <= 10; $i++) {
            cache()->put("stress_test_{$i}", "value_{$i}", 60);
            cache()->get("stress_test_{$i}");
            $operations['cache'][] = "Cache {$i}";
        }

        return response()->json([
            'message' => '✅ Stress test completed!',
            'operations_executed' => [
                'queries' => 10,
                'events' => 5,
                'cache' => 20, // 10 puts + 10 gets
            ],
            'instruction' => 'Check debug bar - Should show many operations!',
            'performance_note' => 'Debug bar should handle high volume without lag',
        ]);
    }
}
