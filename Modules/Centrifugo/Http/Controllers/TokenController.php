<?php

namespace Modules\Centrifugo\Http\Controllers;

use Core\Routing\Attributes\Route;
use Core\Support\Facades\Auth;
use Http\JsonResponse;
use Modules\Centrifugo\Application\UseCases\GenerateConnectionTokenUseCase;

class TokenController
{
    public function __construct(
        private GenerateConnectionTokenUseCase $generateTokenUseCase,
        private Auth $auth,
    ) {
    }

    #[Route('/api/centrifugo/token', method: 'GET', middleware: ['auth:api'])]
    public function generate(): JsonResponse
    {
        // Middleware 'auth:api' đảm bảo rằng người dùng đã được xác thực.
        $user = $this->auth->user();

        if (!$user) {
            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }

        $token = $this->generateTokenUseCase->handle($user);

        return new JsonResponse(['token' => $token]);
    }
}
