<?php

use Core\AppKernel;
use Nyholm\Psr7\Factory\Psr17Factory;

require_once __DIR__ . '/../vendor/autoload.php';

// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$psr17Factory = new Psr17Factory();
$request = $psr17Factory->createServerRequestFromGlobals();
$kernel = new AppKernel();
$app = $kernel->getApplication();

$httpKernel = $app->make(\Core\Contracts\Http\Kernel::class);
$response = $httpKernel->handle($request);

$response->send();
