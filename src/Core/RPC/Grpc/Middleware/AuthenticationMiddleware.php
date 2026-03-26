<?php

namespace Core\RPC\Grpc\Middleware;

/**
 * gRPC Authentication Middleware.
 * 
 * Validates JWT tokens or API keys in gRPC metadata.
 */
class AuthenticationMiddleware
{
    public function __invoke($request, callable $next)
    {
        // Extract metadata (headers) from request
        $metadata = $request->metadata ?? [];
        
        // Check for authorization token
        $token = $metadata['authorization'][0] ?? null;
        
        if (!$token) {
            throw new \RuntimeException('Unauthorized', 16); // UNAUTHENTICATED
        }
        
        // Validate token (integrate with your auth system)
        $user = $this->validateToken($token);
        
        if (!$user) {
            throw new \RuntimeException('Invalid token', 16);
        }
        
        // Attach user to request context
        $request->user = $user;
        
        return $next($request);
    }
    
    private function validateToken(string $token)
    {
        // Remove "Bearer " prefix
        $token = str_replace('Bearer ', '', $token);
        
        // Validate JWT token
        // TODO: Integrate with Core\Auth\TokenGuard
        
        return null; // Placeholder
    }
}
