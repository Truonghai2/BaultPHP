<?php

namespace Modules\User\Http\Controllers;

use Core\Auth\TokenGuard;
use Core\Routing\Attributes\Route;
use Core\Support\Facades\Auth;
use Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CentrifugoController
{
    /**
     * Centrifugo uses this endpoint to authenticate WebSocket connections.
     * It expects a JSON response.
     */
    #[Route('/api/centrifugo/auth', method: 'POST')]
    public function auth(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        $token = $body['token'] ?? null;

        if (!$token) {
            return new JsonResponse(['error' => 'Token not provided'], 400);
        }

        try {
            /** @var TokenGuard $guard */
            $guard = Auth::guard('centrifugo');
            $user = $guard->userFromToken($token);

            if (!$user) {
                return new JsonResponse(['error' => 'Authentication failed'], 401);
            }

            if (!in_array('websocket', $guard->getScopes())) {
                return new JsonResponse(['error' => 'Insufficient scope for WebSocket connection'], 403);
            }

            return new JsonResponse(['user' => (string) $user->getAuthIdentifier()]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Authentication failed'], 401);
        }
    }
}
