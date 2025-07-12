<?php

namespace Modules\User\Http\Controllers;

use Core\Routing\Attributes\Route;
use Http\Response;
use Modules\User\Http\Requests\StoreUserRequest;
use Modules\User\Infrastructure\Models\User; // Giả sử bạn có model này

class UserController
{
    #[Route('/api/users', method: 'POST')]
    public function store(StoreUserRequest $request): Response
    {
        // Nếu code chạy đến đây, nghĩa là request đã được phân quyền và xác thực thành công.
        // Chúng ta có thể an toàn lấy dữ liệu đã được xác thực.
        $validatedData = $request->validated();

        // Giả sử model User của bạn có thể mass-assign
        $user = User::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'password' => password_hash($validatedData['password'], PASSWORD_DEFAULT),
        ]);

        return (new Response())
            ->json(['message' => 'User created successfully.', 'data' => $user], 201);
    }
}