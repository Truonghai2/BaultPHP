<?php

use Core\Routing\Router;

return function (Router $router) {
    $router->get('/user', function () {
        dd("users hoạt động!");
    });
};
