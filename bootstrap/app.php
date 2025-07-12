<?php

use Core\AppKernel;
use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

// App Kernel
$kernel = new AppKernel();
return $kernel->getApplication();
