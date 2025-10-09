<?php

namespace App\Http\Controllers;

use Core\Routing\Attributes\Route;
use Psr\Http\Message\ResponseInterface;

class HomeController extends \Core\Http\Controller
{
    #[Route('/', method: 'GET', name: 'home')]
    public function __invoke(): ResponseInterface
    {
        $data = [
            'version' => config('app.version', '1.0.0'),
            'isBeta' => true,
            'features' => [
                'Kiến trúc Modular theo DDD',
                'Hệ thống Routing linh hoạt',
                'Tích hợp Swoole cho hiệu năng cao',
            ],
        ];

        return response(view('welcome', $data));
    }

    #[Route('/test/cookie', method: 'GET', name: 'test.cookie', group: 'web')]
    public function testCookie(): ResponseInterface
    {
        cookie('bault_test_cookie', 'Xin chào từ BaultFrame! Thời gian: ' . time(), 3600);
        return response('Cookie test route. Check your browser cookies.');
    }
}
