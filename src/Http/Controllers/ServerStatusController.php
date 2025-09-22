<?php

namespace App\Http\Controllers;

use App\Http\JsonResponse;
use Core\Database\Swoole\SwoolePdoPool;
use Core\Database\Swoole\SwooleRedisPool;
use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Core\Server\SwooleServer;

/**
 * Provides real-time status information about the running server,
 * including connection pool statistics.
 */
class ServerStatusController extends Controller
{
}
