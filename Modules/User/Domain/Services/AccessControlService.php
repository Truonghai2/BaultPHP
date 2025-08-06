<?php

namespace Modules\User\Domain\Services;

use App\Exceptions\AuthorizationException;
use Core\Application;
use Core\Auth\Access\Response;
use Core\Cache\CacheManager;
use Core\ORM\Model;
use Modules\User\Domain\Attributes\ParentContext;
use Modules\User\Infrastructure\Models\Context;
use Modules\User\Infrastructure\Models\Permission;
use Modules\User\Infrastructure\Models\Role;
use Modules\User\Infrastructure\Models\RoleAssignment;
use Modules\User\Infrastructure\Models\User;

class AccessControlService
{
    /**
     * A cache for user permissions to avoid redundant database queries within a single request.
     * The new format stores ALL permissions for a user.
     * Format: [userId => [
     *     'is_super' => bool,
     *     'contexts' => [contextId => [
     *          'roles' => [roleId => roleName, ...],
     *          'permissions' => [permissionName => true, ...]
     *      ]],
     * ]]
     */
    private array $permissionCache = [];

    /**
     * A static cache for reflected parent context methods to improve performance.
     * Format: [className => ?ReflectionMethod]
     */
    private static array $reflectionCache = [];

    /**
     * A callback that is run before all other authorization checks.
     */
    protected static ?\Closure $beforeCallback = null;

    /**
     * The registered model-to-policy mappings.
     *
     * @var array<string, string>
     */
    protected array $policies = [];

    private ?CacheManager $cache;

    protected Application $app;

    // Sử dụng Dependency Injection để inject CacheManager.
    // `?CacheManager` cho phép service hoạt động ngay cả khi CacheServiceProvider chưa được đăng ký (hữu ích cho testing).
    public function __construct(Application $app, ?CacheManager $cache = null)
    {
        $this->app = $app;
        $this->cache = $cache;
    }

    /**
     * Register a policy for a given model class.
     *
     * @param  string  $model
     * @param  string  $policy
     * @return void
     */
    public function policy(string $model, string $policy): void
    {
        $this->policies[$model] = $policy;
    }

    /**
     * Check if a user has a specific permission in a given context.
     *
     * @param User $user The user to check.
     * @param string $permissionName The name of the permission.
     * @param Model|Context|null $context The context object (e.g., a Post, a Course, or a Context model).
     * @return bool
     */
    public function check(User $user, string $permissionName, $context = null): bool
    {
        // Cấp 0: Chạy "before" callback nếu có, cho phép ghi đè toàn bộ.
        if (static::$beforeCallback) {
            $result = call_user_func(static::$beforeCallback, $user, $permissionName, $context);
            if (!is_null($result)) {
                return $result;
            }
        }

        // Cấp 0.5: Kiểm tra Policy trước. Nếu policy trả về kết quả, sử dụng nó.
        $policyResult = $this->callPolicyMethod($user, $permissionName, $context);
        if (!is_null($policyResult)) {
            return $policyResult;
        }

        // Cấp 1: Kiểm tra xem toàn bộ quyền của user đã được load vào cache chưa.
        if (!isset($this->permissionCache[$user->id])) {
            $this->loadAndCacheUserPermissions($user);
        }

        // Cấp 2: Kiểm tra trạng thái superuser đã được cache. Đây là bước kiểm tra nhanh nhất.
        if ($this->permissionCache[$user->id]['is_super'] ?? false) {
            return true;
        }

        // Cấp 3: Nếu không phải superuser, tiếp tục kiểm tra quyền cụ thể.
        $context = $this->resolveContext($context);

        // Cấp 4: Kiểm tra quyền dựa trên dữ liệu đã được cache.
        // Logic này sẽ duyệt từ context hiện tại lên các context cha.
        $contextIds = $this->getContextHierarchyIds($context);

        $userContexts = $this->permissionCache[$user->id]['contexts'] ?? [];

        foreach ($contextIds as $contextId) {
            if (isset($userContexts[$contextId]['permissions'][$permissionName])) {
                return true; // Tìm thấy quyền ở cấp này hoặc cấp cha
            }
        }

        return false;
    }

    /**
     * Check if a user has a specific role in a given context, including parent contexts.
     * This method is optimized to use the pre-loaded cache.
     *
     * @param User $user The user to check.
     * @param string $roleName The name of the role.
     * @param Model|Context|null $context The context object.
     * @return bool
     */
    public function hasRole(User $user, string $roleName, $context = null): bool
    {
        // Step 1: Ensure user data is loaded into the request cache.
        if (!isset($this->permissionCache[$user->id])) {
            $this->loadAndCacheUserPermissions($user);
        }

        // Step 2: Resolve the context and its hierarchy.
        $context = $this->resolveContext($context);
        $contextIds = $this->getContextHierarchyIds($context);

        // Step 3: Check the cache for the role in the current context or any parent context.
        $userContexts = $this->permissionCache[$user->id]['contexts'] ?? [];

        foreach ($contextIds as $contextId) {
            $rolesInContext = $userContexts[$contextId]['roles'] ?? [];
            // Since a user has only one role per context, this check is efficient.
            if (in_array($roleName, $rolesInContext, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Call the appropriate method on a policy if one is registered.
     *
     * @param  \Modules\User\Infrastructure\Models\User  $user
     * @param  string  $permission
     * @param  mixed  $context
     * @return bool|null  Returns boolean if a policy method was called, null otherwise.
     */
    protected function callPolicyMethod(User $user, string $permission, $context): ?bool
    {
        $classToAuthorize = is_object($context) ? get_class($context) : $context;

        if (!is_string($classToAuthorize) || !class_exists($classToAuthorize) || !is_subclass_of($classToAuthorize, Model::class)) {
            return null; // Not a policy-related check for a valid model.
        }

        $policyClass = $this->policies[$classToAuthorize] ?? null;
        if (!$policyClass) {
            return null; // No policy registered for this model.
        }

        // Determine the policy method name from the permission name.
        // Convention: 'resource:action' -> 'action'. 'action' -> 'action'.
        $parts = explode(':', $permission);
        $methodName = end($parts);

        $policyInstance = $this->app->make($policyClass);

        // Check for a "before" method on the policy which can intercept the check.
        if (method_exists($policyInstance, 'before')) {
            // The 'before' method receives the user and the ability being checked.
            $beforeResult = $this->app->call([$policyInstance, 'before'], [$user, $permission]);
            if (!is_null($beforeResult)) {
                return $beforeResult;
            }
        }

        // If the policy has the method, call it.
        if (method_exists($policyInstance, $methodName)) {
            $result = null;
            // For actions like 'update', 'delete', the context is a model instance.
            if (is_object($context)) {
                $result = $this->app->call([$policyInstance, $methodName], [$user, $context]);
            } else {
                // For actions like 'create', the context is a class string.
                $result = $this->app->call([$policyInstance, $methodName], [$user]);
            }

            if ($result instanceof Response) {
                if (!$result->allowed()) {
                    // Ném exception trực tiếp từ đây với thông báo tùy chỉnh.
                    throw new AuthorizationException($result->message() ?? 'This action is unauthorized.', 403);
                }
                return true; // Nếu được phép, trả về true
            }

            // Nếu policy trả về boolean, trả về giá trị đó
            return (bool) $result;
        }

        return null;
    }

    /**
     * Resolve a context object from a model or null.
     * This method is now responsible for finding/creating contexts and their hierarchy.
     */
    public function resolveContext($context): Context
    {
        if ($context instanceof Context) {
            return $context;
        }

        if ($context instanceof Model) {
            $level = strtolower(basename(str_replace('\\', '/', get_class($context))));
            $instanceId = $context->getKey();
        } else {
            // The system context (ID 1) must exist. findOrFail ensures this and returns the correct type.
            /** @var Context $systemContext */
            $systemContext = Context::findOrFail(1);
            return $systemContext;
        }

        /** @var Context|null $existingContext */
        $existingContext = Context::where('context_level', '=', $level)
                                  ->where('instance_id', '=', $instanceId)
                                  ->first();

        if ($existingContext) {
            return $existingContext;
        }

        $parentContext = $this->resolveParentContext($context);

        /** @var Context $newContext */
        $newContext = Context::create([
            'parent_id'     => $parentContext->id,
            'context_level' => $level,
            'instance_id'   => $instanceId,
            'depth'         => $parentContext->depth + 1,
            'path'          => $parentContext->path,
        ]);

        $newContext->path .= $newContext->id . '/';
        $newContext->save();

        return $newContext;
    }

    /**
     * A helper method to resolve a context directly by its level and instance ID.
     * This is useful for controllers that don't have a full model instance.
     */
    public function resolveContextByLevelAndId(string $level, int $instanceId): Context
    {
        // This logic is similar to resolveContext, but starts from level/id instead of a model.
        /** @var Context|null $existingContext */
        $existingContext = Context::where('context_level', '=', $level)
                                  ->where('instance_id', '=', $instanceId)
                                  ->first();

        if ($existingContext) {
            return $existingContext;
        }

        // If the context doesn't exist, we cannot create it because we don't know its parent.
        // This should be considered an application error (e.g., trying to assign a role to a non-existent post).
        // For simplicity, we'll throw an exception. A more complex system might return null.
        throw new \InvalidArgumentException("Context for level '{$level}' with ID '{$instanceId}' does not exist.");
    }

    /**
     * Dựa vào convention, tìm context cha từ một model.
     * This new version uses the `#[ParentContext]` attribute for flexibility.
     */
    private function resolveParentContext(Model $model): Context
    {
        $className = get_class($model);

        // Use a static cache to avoid repeated reflection for the same class within a request.
        if (!array_key_exists($className, self::$reflectionCache)) {
            self::$reflectionCache[$className] = null; // Default to null
            $reflector = new \ReflectionClass($className);

            foreach ($reflector->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                // Find the first method with the ParentContext attribute.
                if (!empty($method->getAttributes(ParentContext::class))) {
                    self::$reflectionCache[$className] = $method;
                    break;
                }
            }
        }

        /** @var ?\ReflectionMethod $parentMethod */
        $parentMethod = self::$reflectionCache[$className];

        if ($parentMethod) {
            // Invoke the method (e.g., $model->course()) to get the parent model instance.
            $parentModel = $parentMethod->invoke($model);
            if ($parentModel instanceof Model) {
                return $this->resolveContext($parentModel);
            }
        }

        // Nếu không tìm thấy cha cụ thể, mặc định cha là hệ thống
        return $this->resolveContext(null);
    }

    private function getContextHierarchyIds(Context $context): array
    {
        // Sử dụng Materialized Path để lấy tất cả ID trong chuỗi phân cấp.
        // Ví dụ: path '1/5/12/' sẽ trả về [1, 5, 12].
        return explode('/', rtrim($context->path, '/'));
    }

    /**
     * Loads all permissions for a given user and caches them.
     * This is the core optimization to prevent N+1 queries.
     */
    private function loadAndCacheUserPermissions(User $user): void
    {
        // Cấp 1: Kiểm tra cache bền vững (Redis) trước
        $cacheKey = "acl:all_perms:{$user->id}";
        if ($this->cache) {
            $cachedPermissions = $this->cache->store()->get($cacheKey);
            if ($cachedPermissions) {
                // Nếu có trong cache Redis, giải nén và lưu vào cache tĩnh của request
                $this->permissionCache[$user->id] = json_decode($cachedPermissions, true);
                return;
            }
        }

        // Cấp 2: Nếu không có trong cache, thực hiện truy vấn CSDL
        $permissionsByContext = [];
        $isSuperUser = false;

        // Query 1: Get all role assignments for the user, eager loading the role and its permissions.
        // This is highly efficient, reducing N+1 problems.
        $assignments = RoleAssignment::where('user_id', '=', $user->id)->with('role.permissions')->get();

        if ($assignments->isNotEmpty()) {
            // Xử lý kết quả trong PHP để xây dựng cấu trúc cache
            foreach ($assignments as $assignment) {
                // The role and its permissions are already loaded.
                $role = $assignment->role;
                if ($role) {
                    $contextId = $assignment->context_id;

                    // Initialize context cache if it doesn't exist
                    if (!isset($permissionsByContext[$contextId])) {
                        $permissionsByContext[$contextId] = ['roles' => [], 'permissions' => []];
                    }

                    // Cache the role name and ID
                    $permissionsByContext[$contextId]['roles'][$role->id] = $role->name;

                    // Cache all permissions for that role
                    if ($role->permissions) {
                        foreach ($role->permissions as $permission) {
                            $permissionsByContext[$contextId]['permissions'][$permission->name] = true;
                        }
                    }
                }
            }
        }

        // Sau khi xây dựng map quyền, kiểm tra xem người dùng có quyền superuser không.
        // Chúng ta kiểm tra trên dữ liệu vừa lấy được để tránh truy vấn CSDL lần nữa.
        $superPermission = config('auth.super_admin_permission', 'system.manage-all');
        // Context ID 1 is the system (root) context.
        if (isset($permissionsByContext[1]['permissions'][$superPermission])) {
            $isSuperUser = true;
        }

        // Xây dựng cấu trúc cache hoàn chỉnh
        $this->permissionCache[$user->id] = [
            'is_super' => $isSuperUser,
            'contexts' => $permissionsByContext,
        ];

        // Cấp 3: Lưu cấu trúc hoàn chỉnh vào cache bền vững (Redis)
        if ($this->cache) {
            // Cache trong 1 giờ (3600 giây)
            $this->cache->store()->put($cacheKey, json_encode($this->permissionCache[$user->id]), 3600);
        }
    }

    /**
     * Register a callback to be executed before all authorization checks.
     *
     * The callback will receive the user, the permission name, and the original context.
     * `function (User $user, string $permission, mixed $context): ?bool`
     *
     * @param  \Closure  $callback
     * @return void
     */
    public static function before(\Closure $callback): void
    {
        static::$beforeCallback = $callback;
    }
}
