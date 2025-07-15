<?php

namespace Http\Middleware;

use Closure;
use Core\Contracts\Http\Middleware;
use Core\ORM\Model;
use Http\Request;
use ReflectionMethod;
use ReflectionParameter;

class SubstituteBindings implements Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        // Đối tượng Route được gán trực tiếp vào Request trong Kernel.
        $route = $request->route;

        // Nếu không có route hoặc route không có tham số, bỏ qua.
        if (!$route || empty($route->parameters)) {
            return $next($request);
        }

        // Lấy ra các tham số đã được type-hint trong controller method
        $methodParameters = $this->getMethodParameters($route->handler);
 
        foreach ($methodParameters as $parameter) {
            $paramName = $parameter->getName();
            $paramType = $parameter->getType();

            // Kiểm tra nếu tham số route tồn tại, chưa được resolve thành object,
            // và được type-hint là một class kế thừa từ Model.
            if (
                isset($route->parameters[$paramName]) &&
                !is_object($route->parameters[$paramName]) &&
                $paramType && !$paramType->isBuiltin() &&
                is_subclass_of($paramType->getName(), Model::class)
            ) {
                /** @var Model $modelClass */
                $modelClass = $paramType->getName();
                $value = $route->parameters[$paramName];

                // Lấy khóa binding tùy chỉnh từ route, hoặc mặc định là 'id'.
                // Điều này dựa vào việc Router đã phân tích cú pháp {param:key}.
                $bindingField = $route->getBindingField($paramName);

                // CẢI TIẾN: Nếu không có binding field nào được chỉ định trên route,
                // kiểm tra xem model có định nghĩa một route key tùy chỉnh hay không.
                // Điều này cho phép model tự quyết định key mặc định của nó (ví dụ: 'slug').
                if ($bindingField === 'id' && method_exists($modelClass, 'getRouteKeyName')) {
                    $modelInstanceForMethod = new $modelClass();
                    $bindingField = $modelInstanceForMethod->getRouteKeyName();
                }

                // Thực hiện truy vấn. firstOrFail() sẽ ném ra exception (dẫn đến 404) nếu không tìm thấy.
                $modelInstance = $modelClass::where($bindingField, $value)->firstOrFail();

                // Thay thế giá trị tham số (ID hoặc slug) bằng instance của Model đã được resolve.
                $route->parameters[$paramName] = $modelInstance;
            }
        }

        return $next($request);
    }

    /**
     * Get the parameters for the route handler method.
     *
     * @param  array|callable  $handler
     * @return ReflectionParameter[]
     */
    protected function getMethodParameters(array|callable $handler): array
    {
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            try {
                return (new ReflectionMethod($class, $method))->getParameters();
            } catch (\ReflectionException $e) {
                // Bỏ qua nếu không thể reflect method
                return [];
            }
        }
        return [];
    }
}