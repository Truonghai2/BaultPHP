<?php

namespace Modules\Admin\Http\Controllers;

use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Psr\Http\Message\ResponseInterface;

class CorsController extends Controller
{
    #[Route('/admin/cors', method: 'GET', name: 'admin.cors', middleware: ['auth'])]
    public function index(): ResponseInterface
    {
        return response(view('admin::cors.index'));
    }
}
