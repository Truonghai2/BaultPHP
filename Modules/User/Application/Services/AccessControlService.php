<?php

namespace Modules\User\Application\Services;

use Core\Cache\CacheManager;
use Modules\User\Domain\Attributes\ParentContext;
use Modules\User\Infrastructure\Models\Context;
use Modules\User\Infrastructure\Models\Model;
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
     *     'contexts' => [contextId => [permissionName => true, ...]],
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

    private ?CacheManager $cache;

    // Sử dụng Dependency Injection để inject CacheManager.
    // `?CacheManager` cho phép service hoạt động ngay cả khi CacheServiceProvider chưa được đăng ký (hữu ích cho testing).
    public function __construct(?CacheManager $cache = null)
    {
        $this->cache = $cache;
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

        $userPermissions = $this->permissionCache[$user->id]['contexts'] ?? [];

        foreach ($contextIds as $contextId) {
            if (isset($userPermissions[$contextId][$permissionName])) {
                return true; // Tìm thấy quyền ở cấp này hoặc cấp cha
            }
        }

        return false;
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

        // 1. Xác định level và instance_id
        if ($context instanceof Model) {
            $level = strtolower(basename(str_replace('\\', '/', get_class($context))));
            $instanceId = $context->getKey();
        } else {
            // Nếu không có context, mặc định là context hệ thống đã được tạo sẵn.
            return Context::find(1);
        }

        // 2. Tìm context trong DB
        $existingContext = Context::where('context_level', $level)
                                  ->where('instance_id', $instanceId)
                                  ->first();

        if ($existingContext) {
            return $existingContext;
        }

        // 3. Nếu không có, tạo context mới và xác định cha của nó
        $parentContext = $this->resolveParentContext($context);

        $newContext = Context::create([
            'parent_id'     => $parentContext->id,
            'context_level' => $level,
            'instance_id'   => $instanceId,
            'depth'         => $parentContext->depth + 1,
            'path'          => 'temp' // Sẽ cập nhật ngay sau đây
        ]);

        // Xây dựng path dựa trên cha và cập nhật lại
        $newContext->path = $parentContext->path . $newContext->id . '/';
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
        $existingContext = Context::where('context_level', $level)
                                  ->where('instance_id', $instanceId)
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
            $cachedPermissions = $this->cache->get($cacheKey);
            if ($cachedPermissions) {
                // Nếu có trong cache Redis, giải nén và lưu vào cache tĩnh của request
                $this->permissionCache[$user->id] = json_decode($cachedPermissions, true);
                return;
            }
        }

        // Cấp 2: Nếu không có trong cache, thực hiện truy vấn CSDL
        $permissionsByContext = [];
        $isSuperUser = false;

        // Query 1: Lấy tất cả các vai trò và ngữ cảnh mà người dùng được gán
        $assignments = RoleAssignment::where('user_id', $user->id)->get();

        if ($assignments->isNotEmpty()) {
            $roleIds = $assignments->map(fn($a) => $a->role_id)->unique()->all();

            // Query 2: Lấy tất cả các quyền gắn với các vai trò đó
            // Đây là một truy vấn JOIN hiệu quả
            $rolePermissions = Role::query()
                ->join('permission_role', 'roles.id', '=', 'permission_role.role_id')
                ->join('permissions', 'permission_role.permission_id', '=', 'permissions.id')
                ->whereIn('roles.id', $roleIds)
                ->select('roles.id as role_id', 'permissions.name as permission_name')
                ->get()
                ->groupBy('role_id'); // Nhóm theo role_id để dễ tra cứu

            // Xử lý kết quả trong PHP để xây dựng cấu trúc cache
            foreach ($assignments as $assignment) {
                $contextId = $assignment->context_id;
                $roleId = $assignment->role_id;

                if (!isset($permissionsByContext[$contextId])) {
                    $permissionsByContext[$contextId] = [];
                }

                if (isset($rolePermissions[$roleId])) {
                    foreach ($rolePermissions[$roleId] as $permission) {
                        $permissionsByContext[$contextId][$permission->permission_name] = true;
                    }
                }
            }
        }

        // Sau khi xây dựng map quyền, kiểm tra xem người dùng có quyền superuser không.
        // Chúng ta kiểm tra trên dữ liệu vừa lấy được để tránh truy vấn CSDL lần nữa.
        $superPermission = config('auth.super_admin_permission', 'system.manage-all');
        // Context ID 1 là context hệ thống (root).
        if (isset($permissionsByContext[1][$superPermission])) {
            $isSuperUser = true;
        }

        // Xây dựng cấu trúc cache hoàn chỉnh
        $this->permissionCache[$user->id] = [
            'is_super' => $isSuperUser,
            'contexts' => $permissionsByContext
        ];

        // Cấp 3: Lưu cấu trúc hoàn chỉnh vào cache bền vững (Redis)
        if ($this->cache) {
            // Cache trong 1 giờ (3600 giây)
            $this->cache->set($cacheKey, json_encode($this->permissionCache[$user->id]), 'EX', 3600);
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