<?php

use Core\Routing\Router;
use Modules\Page\Http\Controllers\HomeController;

return function (Router $router) {
    $router->get('/', [HomeController::class, 'index']);
};
