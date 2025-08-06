<?php

namespace Modules\User\Http\Controllers;

use Core\Routing\Attributes\Route;
use Core\Support\Facades\Auth;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ProfileController
{
    #[Route('/', method: 'GET')]
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        // Trả về view 'welcome' đã có sẵn trong project của bạn.
        // Hàm view() và response() là các helper function của framework.
        return response(view('welcome'));
    }

    #[Route('/api/profile', method: 'GET', middleware: [\Http\Middleware\AuthMiddleware::class])]
    public function show(ServerRequestInterface $request)
    {
        /** @var \Modules\User\Infrastructure\Models\User|null $user */
        $user = Auth::user();

        if (!$user) {
            return ['error' => 'User not found, although authenticated.'];
        }

        return [
            'id' => $user->getAuthIdentifier(),
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
