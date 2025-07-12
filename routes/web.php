<?php

use Core\Routing\Router;

return function (Router $router) {
    $router->get('/', function () {
        dd("Trang chủ hoạt động!");
    });
};
