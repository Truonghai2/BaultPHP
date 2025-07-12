<?php

use Core\AppKernel;
use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

$config = require base_path('config/database.php');

$default = $config['default'] ?? 'mysql';

if (!isset($config['connections'][$default])) {
    throw new InvalidArgumentException("Database connection [$default] not configured.");
}

// App Kernel
$kernel = new AppKernel();
return $kernel->getApplication();
