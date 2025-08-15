<?php

namespace Http\Controllers;

use Core\Routing\Attributes\Route;
use Psr\Http\Message\ResponseInterface;

class HomeController extends \Core\Http\Controller
{
    #[Route('/', method: 'GET', name: 'home')]
    public function __invoke(): ResponseInterface
    {
        // Chuẩn bị một mảng dữ liệu để truyền sang view.
        // Mỗi key trong mảng này (ví dụ: 'version', 'isBeta', 'features')
        // sẽ trở thành một biến tương ứng trong file Blade ($version, $isBeta, $features).
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
}
