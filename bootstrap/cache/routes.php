<?php

return array (
  'POST' => 
  array (
    '/api/centrifugo/auth' => 
    array (
      'handler' => 
      array (
        0 => 'Modules\\User\\Http\\Controllers\\CentrifugoController',
        1 => 'auth',
      ),
      'middleware' => 
      array (
      ),
    ),
    '/api/users' => 
    array (
      'handler' => 
      array (
        0 => 'Modules\\User\\Http\\Controllers\\UserController',
        1 => 'store',
      ),
      'middleware' => 
      array (
      ),
    ),
  ),
  'GET' => 
  array (
    '/' => 
    array (
      'handler' => 
      array (
        0 => 'Modules\\User\\Http\\Controllers\\ProfileController',
        1 => 'index',
      ),
      'middleware' => 
      array (
      ),
    ),
    '/api/profile' => 
    array (
      'handler' => 
      array (
        0 => 'Modules\\User\\Http\\Controllers\\ProfileController',
        1 => 'show',
      ),
      'middleware' => 
      array (
        0 => 'Http\\Middleware\\AuthMiddleware',
      ),
    ),
  ),
);
