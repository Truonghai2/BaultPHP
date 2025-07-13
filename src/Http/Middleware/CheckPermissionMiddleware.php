<?php

namespace Http\Middleware;

use App\Exceptions\AuthorizationException;
use App\Exceptions\NotFoundException;
use Core\Support\Facades\Auth;
use Http\Request;
use Core\ORM\Model;
use Closure;

class CheckPermissionMiddleware
{
    /**
     * A static cache to store the mapping of route parameters to their model classes.
     * This avoids expensive reflection on every request.
     * Format: ['Controller@method:paramName' => 'App\Models\ModelClass']
     */
    private static array $contextModelCache = [];
    /**
     * Handle an incoming request.
     *
     * @param  \Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$args The permission name, and optionally the context parameter name.
     * @return mixed
     * @throws \App\Exceptions\AuthorizationException
     */
    public function handle(Request $request, Closure $next, ...$args)
    {
        $user = Auth::user();

        if (!$user) {
            throw new AuthorizationException('Unauthenticated.', 401);
        }

        if (empty($args)) {
            // Không có quyền nào được chỉ định, cho qua.
            return $next($request);
        }

        $permission = $args[0];
        $contextParameterName = $args[1] ?? null;

        $contextObject = null;
        if ($contextParameterName) {
            $contextObject = $this->resolveContextObject($request, $contextParameterName);

            // Nếu yêu cầu ngữ cảnh nhưng không tìm thấy đối tượng (ví dụ: post ID không tồn tại),
            // thì đây là lỗi 404 Not Found, không phải 403 Forbidden.
            if (is_null($contextObject)) {
                throw new NotFoundException("The resource for context '{$contextParameterName}' was not found.");
            }
        }

        // Phương thức `can()` trên User model sẽ gọi AccessControlService.
        // Nó có thể xử lý cả trường hợp context là null (kiểm tra toàn hệ thống)
        // và trường hợp có context object.
        if (!$user->can($permission, $contextObject)) {
            throw new AuthorizationException('This action is unauthorized.', 403);
        }

        return $next($request);
    }

    /**
     * Resolve the context model instance from the route.
     *
     * @param  \Http\Request  $request
     * @param  string  $parameterName
     * @return \Core\ORM\Model|null
     * @throws \App\Exceptions\AuthorizationException
     */
    protected function resolveContextObject(Request $request, string $parameterName): ?Model
    {
        if (!$request->route) {
            return null;
        }

        $contextId = $request->route->parameters[$parameterName] ?? null;
        if (is_null($contextId)) {
            // Tham số context không tồn tại trong route, đây là lỗi cấu hình.
            throw new AuthorizationException("Context parameter '{$parameterName}' not found in route.", 500);
        }

        $modelClass = $this->getModelClassForParameter($request->route, $parameterName);

        if (!$modelClass) {
            throw new AuthorizationException("Could not resolve model for context parameter '{$parameterName}'. Is it type-hinted in the controller method?", 500);
        }

        // Tìm model trong DB. Nếu không tìm thấy, trả về null.
        // Middleware sẽ xử lý việc này như một lỗi 404.
        return $modelClass::find($contextId);
    }

    /**
     * Find and cache the model class name for a given route parameter using reflection.
     */
    protected function getModelClassForParameter(\Core\Routing\Route $route, string $parameterName): ?string
    {
        [$controllerClass, $methodName] = $route->handler;
        $cacheKey = "{$controllerClass}@{$methodName}:{$parameterName}";

        if (isset(self::$contextModelCache[$cacheKey])) {
            return self::$contextModelCache[$cacheKey];
        }
        
        try {
            $reflector = new \ReflectionMethod($controllerClass, $methodName);
            foreach ($reflector->getParameters() as $parameter) {
                if ($parameter->getName() === $parameterName) {
                    $type = $parameter->getType();
                    if ($type && !$type->isBuiltin() && is_subclass_of($modelClass = $type->getName(), Model::class)) {
                        return self::$contextModelCache[$cacheKey] = $modelClass;
                    }
                }
            }
        } catch (\ReflectionException $e) {
            throw new AuthorizationException("Failed to reflect route handler for context resolution.", 500);
        }

        return null;
    }
}