<?php

namespace Core;

use Core\Application;

abstract class ServiceProvider
{
    public function __construct(protected Application $app)
    {
    }

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
