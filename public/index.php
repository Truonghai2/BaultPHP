<?php

use Core\AppKernel;
use Http\Request;

require_once __DIR__ . '/../vendor/autoload.php';

// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$request = Request::capture();
$kernel = new AppKernel();

$response = $kernel->handle($request);

$response->send();
