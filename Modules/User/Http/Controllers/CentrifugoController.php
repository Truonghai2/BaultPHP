<?php

namespace Modules\User\Http\Controllers;

use Core\Routing\Attributes\Route;
use Core\Support\Facades\Auth;
use Http\Request;
use Http\Response;

class CentrifugoController
{
    /**
     * Centrifugo uses this endpoint to authenticate WebSocket connections.
     * It expects a JSON response.
     */
    #[Route('/api/centrifugo/auth', method: 'POST')]
    public function auth(Request $request): Response
    {
        // Centrifugo sends the token from the client in the request body.
        // We assume the client sends { "token": "jwt_token_here" }
        $token = $request->input('token');

        if (!$token) {
            return (new Response())->setStatusCode(401)->json(['error' => 'Token not provided.']);
        }

        try {
            $user = Auth::guard('jwt_ws')->userFromToken($token);

            if (!$user) {
                return (new Response())->setStatusCode(401)->json(['error' => 'Invalid token.']);
            }

            // Success! Return the user's ID to Centrifugo.
            // Centrifugo will associate this connection with this user ID.
            // You can also subscribe the user to their personal channels here.
            return (new Response())->json(['user' => (string) $user->getAuthIdentifier()]);
        } catch (\Throwable $e) {
            return (new Response())->setStatusCode(500)->json(['error' => 'Authentication service error.']);
        }
    }
}