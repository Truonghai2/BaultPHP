<?php 

namespace App\Console;

class Kernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * @var array
     */
    protected $middleware = [
        // Add your global middleware here
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            // Add your web middleware here
        ],
        'api' => [
            // Add your API middleware here
        ],
    ];
}