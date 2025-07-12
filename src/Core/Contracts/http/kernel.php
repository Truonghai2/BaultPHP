<?php 

namespace Core\Contracts\Http;

use Http\Request;
use Http\Response;

interface Kernel
{
    public function handle(Request $request): Response;
    public function terminate(Request $request, Response $response): void;
}