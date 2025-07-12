<?php

namespace Http\Middleware;

use App\Exceptions\AuthorizationException;
use Core\Support\Facades\Auth;
use Http\Request;

class JwtVerifyTokenMiddleware
{
    public function handle(Request $request, \Closure $next)
    {
        // JwtGuard sẽ tự động giải quyết người dùng từ token.
        // Chúng ta chỉ cần kích hoạt nó bằng cách gọi một phương thức trên guard.
        $user = Auth::guard('api')->user();
        
        // Nếu có token nhưng không hợp lệ, người dùng sẽ là null.
        // Chúng ta chỉ ném exception nếu có token nhưng xác thực thất bại.
        if ($request->bearerToken() && is_null($user)) {
            throw new AuthorizationException('Unauthenticated.', 401);
        }

        return $next($request);
    }
}
