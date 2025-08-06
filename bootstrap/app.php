<?php

use Core\AppKernel;
use Core\Application;
use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

// Create a new application instance.
$app = new Application(
    realpath(__DIR__ . '/../'),
);

// Load environment variables from .env file.
if (file_exists($app->basePath('.env'))) {
    $dotenv = Dotenv::createImmutable($app->basePath());
    $dotenv->load();
}

// App Kernel
$kernel = new AppKernel($app);
return $kernel->getApplication();
