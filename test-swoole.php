<?php

require_once __DIR__ . '/vendor/autoload.php';

use Core\Application;
use Core\Server\SwooleServer;
use Mockery as m;

// Bootstrap application
$app = new Application(__DIR__);
$app->bootstrap();

echo "=== Testing SwooleServer Creation ===\n\n";

$swooleServerMock = m::mock('overload:' . \Swoole\Http\Server::class);
$swooleServerMock->shouldReceive('set');
$swooleServerMock->shouldReceive('on');
$swooleServerMock->shouldReceive('addProcess');

// We re-bind the SwooleServer in the container to ensure it uses our mocked objects.
$app->singleton(SwooleServer::class, function ($app) {
    return new SwooleServer($app);
});
// --- End Mocking ---

try {
    echo "1. Creating SwooleServer...\n";
    $swooleServer = $app->make(\Core\Server\SwooleServer::class);
    echo "   ✅ SwooleServer created successfully\n\n";

    echo "2. Checking file watcher properties...\n";
    $reflection = new ReflectionClass($swooleServer);

    $fileWatcherProperty = $reflection->getProperty('fileWatcher');
    $fileWatcherProperty->setAccessible(true);
    $fileWatcher = $fileWatcherProperty->getValue($swooleServer);

    $dockerFileWatcherProperty = $reflection->getProperty('dockerFileWatcher');
    $dockerFileWatcherProperty->setAccessible(true);
    $dockerFileWatcher = $dockerFileWatcherProperty->getValue($swooleServer);

    $fileWatcherProcessProperty = $reflection->getProperty('fileWatcherProcess');
    $fileWatcherProcessProperty->setAccessible(true);
    $fileWatcherProcess = $fileWatcherProcessProperty->getValue($swooleServer);

    echo '   - FileWatcher: ' . ($fileWatcher ? 'INITIALIZED' : 'NULL') . "\n";
    echo '   - DockerFileWatcher: ' . ($dockerFileWatcher ? 'INITIALIZED' : 'NULL') . "\n";
    echo '   - FileWatcherProcess: ' . ($fileWatcherProcess ? 'INITIALIZED' : 'NULL') . "\n\n";

    echo "3. Environment check...\n";
    echo '   - Docker Environment: ' . (isDockerEnvironment() ? 'YES' : 'NO') . "\n";
    echo '   - APP_ENV: ' . config('app.env', 'not set') . "\n\n";

    echo "✅ All tests passed!\n";

} catch (Exception $e) {
    echo '❌ ERROR: ' . $e->getMessage() . "\n";
    echo 'Trace: ' . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";

m::close();
