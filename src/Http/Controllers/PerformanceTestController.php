<?php

namespace App\Http\Controllers;

use Core\Application;
use Core\Http\Controller;

class PerformanceTestController extends Controller
{
    public function __construct(protected Application $app)
    {
    }
}
