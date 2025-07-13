<?php

namespace Core\Contracts\Http;

use Http\Request;
use Http\Response;

interface Middleware
{
    public function handle(Request $request, \Closure $next, ...$guards): Response;
}