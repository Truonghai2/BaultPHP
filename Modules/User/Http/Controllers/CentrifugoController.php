<?php

namespace Modules\User\Http\Controllers;

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
        // Centrifugo sends the token from the client in the request body.
        // We assume the client sends { "token": "jwt_token_here" }
        $body = $request->getParsedBody();
        $token = $body['token'];

        if (!$token) {
            return new JsonResponse(['error' => 'Token not provided'], 400);
        }

        try {
            $user = Auth::guard('jwt_ws')->userFromToken($token);

            if (!$user) {
                return new JsonResponse(['error' => 'Authentication failed'], 401);
            }

            // Success! Return the user's ID to Centrifugo.
            // Centrifugo will associate this connection with this user ID.
            // You can also subscribe the user to their personal channels here.
            return new JsonResponse(['user' => (string) $user->getAuthIdentifier()]);
        } catch (\Throwable $e) {
            // It's often better to return a 401 for any failure to prevent leaking server state.
            return new JsonResponse(['error' => 'Authentication failed'], 401);
        }
    }
}
