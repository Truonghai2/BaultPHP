<?php

namespace Http\Middleware;

use App\Exceptions\AuthorizationException;
use Core\Auth\JWT;
use Core\Support\Facades\Auth;
use Http\Request;
use Modules\User\Infrastructure\Models\User;

class JwtVerifyTokenMiddleware
{
    public function handle(Request $request, \Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return $next($request); 
        }

        try {
            $payload = JWT::decode($token, env('JWT_SECRET', 'secret'));

            if ($payload && isset($payload->sub)) {
                $user = User::find($payload->sub);

                if ($user) {
                    Auth::setUser($user); 
                    $request->setAttribute('user', $user);
                }
            }

            $request->setAttribute('jwt', $payload);
        } catch (\Exception $e) {
            // Ném exception để Kernel có thể bắt và xử lý thành response 401 chuẩn.
            throw new AuthorizationException("Invalid or expired token.", 401);
        }

        return $next($request);
    }
    
}
