<?php

namespace Modules\Admin\Http\Controllers;

use Core\Http\Controller;
use Core\Routing\Attributes\Route;

class DashboardController extends Controller
{
    #[Route('/admin', method: 'GET', name: 'admin.dashboard', middleware: ['auth'], group: 'web')]
    public function index(): \Psr\Http\Message\ResponseInterface
    {
        return response(view('admin::dashboard.index'));
    }
}
