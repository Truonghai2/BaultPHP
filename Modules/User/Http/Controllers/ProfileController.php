<?php

namespace Modules\User\Http\Controllers;

use Core\Routing\Attributes\Route;
use Core\Support\Facades\Auth;
use Http\Request;

class ProfileController
{
    #[Route('/', method: 'GET')]
    public function index(Request $request)
    {
        dd("ProfileController@index called");
    }

    #[Route('/api/profile', method: 'GET', middleware: [\Http\Middleware\AuthMiddleware::class])]
    public function show(Request $request)
    {
        // Middleware AuthMiddleware đã chạy và xác thực người dùng.
        // Chúng ta có thể an toàn lấy thông tin người dùng từ Auth facade.
        $user = Auth::user();

        if (!$user) {
            // Trường hợp này hiếm khi xảy ra nếu middleware hoạt động đúng
            return ['error' => 'User not found, although authenticated.'];
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}