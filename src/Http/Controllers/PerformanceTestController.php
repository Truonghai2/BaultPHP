<?php

namespace App\Http\Controllers;

use App\Http\JsonResponse;
use Core\Application;
use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Core\Support\Benchmark;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class PerformanceTestController extends Controller
{
    public function __construct(protected Application $app)
    {
    }
}
