<?php

namespace App\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * SPA-specific CORS Middleware
 * 
 * Handles CORS for SPA navigation requests on web routes.
 * More lenient than API CORS but still secure.
 */
class SpaCorsMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        
        // Only apply to SPA navigation requests
        $isSpaRequest = $request->hasHeader('X-SPA-NAVIGATE') 
            || $request->hasHeader('X-Requested-With');
        
        if (!$isSpaRequest) {
            return $response;
        }

        $requestOrigin = $request->getHeaderLine('Origin');
        
        // If no origin header, it's a same-origin request
        if (empty($requestOrigin)) {
            return $response;
        }

        $allowedOrigins = $this->getAllowedOrigins();
        
        // Check if origin is allowed
        if ($this->isOriginAllowed($requestOrigin, $allowedOrigins)) {
            $response = $response->withHeader('Access-Control-Allow-Origin', $requestOrigin);
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
            $response = $response->withHeader('Vary', 'Origin');
            
            // Only for preflight
            if ($request->getMethod() === 'OPTIONS') {
                $response = $response->withHeader(
                    'Access-Control-Allow-Methods',
                    'GET, POST, PUT, DELETE, OPTIONS'
                );
                $response = $response->withHeader(
                    'Access-Control-Allow-Headers',
                    'Content-Type, X-Requested-With, X-CSRF-TOKEN, X-SPA-NAVIGATE, Authorization'
                );
                $response = $response->withHeader('Access-Control-Max-Age', '86400');
            }
        }

        return $response;
    }

    /**
     * Get allowed origins from config
     */
    private function getAllowedOrigins(): array
    {
        $origins = config('cors.allowed_origins', []);
        
        // Add common development origins if in local environment
        if (config('app.env') === 'local') {
            $developmentOrigins = [
                'http://localhost:3000',
                'http://localhost:5173',
                'http://localhost:8080',
                'http://127.0.0.1:3000',
                'http://127.0.0.1:5173',
                'http://127.0.0.1:8080',
            ];
            $origins = array_merge($origins, $developmentOrigins);
        }
        
        return array_unique($origins);
    }

    /**
     * Check if origin is allowed
     */
    private function isOriginAllowed(string $origin, array $allowedOrigins): bool
    {
        // Exact match
        if (in_array($origin, $allowedOrigins, true)) {
            return true;
        }
        
        // Wildcard match
        if (in_array('*', $allowedOrigins, true)) {
            return true;
        }
        
        // Pattern match for same domain but different port (development)
        if (config('app.env') === 'local') {
            $originHost = parse_url($origin, PHP_URL_HOST);
            $appHost = parse_url(config('app.url'), PHP_URL_HOST);
            
            if ($originHost === $appHost || 
                in_array($originHost, ['localhost', '127.0.0.1'])) {
                return true;
            }
        }
        
        return false;
    }
}

