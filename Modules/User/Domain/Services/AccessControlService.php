<?php

namespace Modules\User\Domain\Services;

use Amp\Future;
use Amp\Sync\KeyedMutex;
use Amp\Sync\Mutex;
use App\Exceptions\AuthorizationException;
use Core\Application;
use Core\Auth\Access\Response;
use Core\Cache\CacheManager;
use Core\ORM\Model;
use Modules\User\Domain\Attributes\ParentContext;
use Modules\User\Infrastructure\Models\Context;
use Modules\User\Infrastructure\Models\RoleAssignment;
use Modules\User\Infrastructure\Models\User;

/**
 * The central service for handling all authorization logic.
 *
 * This service provides a flexible, context-aware, and high-performance way to check user permissions.
 * It leverages policies, roles, and a multi-level caching strategy to avoid database bottlenecks.
 */
class AccessControlService
{
    /**
     * The ID of the system-level context, which is the root for all permissions.
     */
    private const SYSTEM_CONTEXT_ID = 1;

    /**
     * In-memory cache for user permissions during a single request to avoid redundant DB queries.
     * Format: [userId => ['contexts' => [contextId => ['roles' => [...], 'permissions' => [...]]]]]
     */
    private array $permissionCache = [];
    
    /**
     * Cache for role-permission matrix to avoid repeated joins.
     * Format: [roleId => [permissionName1, permissionName2, ...]]
     * This is shared across all users and contexts.
     */
    private static ?array $rolePermissionMatrix = null;
    
    /**
     * Cache for context hierarchy paths to avoid repeated parsing.
     * Format: [contextId => [parentIds...]]
     */
    private array $contextHierarchyCache = [];

    /**
     * In-memory cache for resolved Context objects during a single request.
     * Format: ["level:instanceId" => Context]
     */
    private array $resolvedContextCache = [];

    /**
     * Static cache for parent context reflection methods to improve performance.
     * Format: [className => ?ReflectionMethod]
     */
    private static array $reflectionCache = [];

    /**
     * The registered model-to-policy mappings.
     * @var array<string, string>
     */
    protected array $policies = [];

    /**
     * @var callable[]
     */
    private array $beforeCallbacks = [];

    /** @var \Psr\SimpleCache\CacheInterface|null */
    private ?\Psr\SimpleCache\CacheInterface $cacheStore = null;

    /** @var Context|null */
    private ?Context $systemContext = null;

    /** @var KeyedMutex|null */
    private ?KeyedMutex $permissionBuildMutex = null;

    /**
     * @param Application $app The application container.
     * @param CacheManager|null $cacheManager The cache manager. Can be null to operate without a
     *                                 persistent cache, which is useful for testing.
     */
    public function __construct(
        protected Application $app,
        ?CacheManager $cacheManager = null,
    ) {
        $this->cacheStore = $cacheManager?->store();
        // Chỉ khởi tạo mutex nếu chúng ta đang trong môi trường async thực sự
        if (class_exists(Mutex::class)) {
            $this->permissionBuildMutex = new KeyedMutex();
        }
    }

    /**
     * Register a policy for a given model class.
     */
    public function policy(string $model, string $policy): void
    {
        $this->policies[$model] = $policy;
    }

    /**
     * Register a callback to be executed before all other authorization checks.
     */
    public function before(callable $callback): void
    {
        $this->beforeCallbacks[] = $callback;
    }

    /**
     * Check if a user has a specific permission in a given context.
     *
     * @param User $user The user to check.
     * @param string $permissionName The name of the permission.
     * @param \Core\ORM\Model|Context|null $context The context (e.g., a Post model, a Course model).
     */
    public function check(User $user, string $permissionName, $context = null): bool
    {
        // 1. Check "before" callbacks first (e.g., for super-admins)
        foreach ($this->beforeCallbacks as $before) {
            $result = $this->app->call($before, ['user' => $user, 'ability' => $permissionName]);
            if (!is_null($result)) {
                return $result;
            }
        }

        // 2. Check registered policies
        $policyResult = $this->callPolicyMethod($user, $permissionName, $context);
        if (!is_null($policyResult)) {
            return $policyResult;
        }

        // 3. Load user permissions if not cached
        if (!isset($this->permissionCache[$user->id])) {
            $this->loadAndCacheUserPermissions($user);
        }

        // 4. Resolve context and get hierarchy
        $context = $this->resolveContext($context);
        $contextIds = $this->getContextHierarchyIds($context);
        $userContexts = $this->permissionCache[$user->id]['contexts'] ?? [];

        // 5. Performance optimization: Use isset() for O(1) lookup instead of array iteration
        foreach ($contextIds as $contextId) {
            // Fast path: Direct permission check (O(1))
            if (isset($userContexts[$contextId]['permissions'][$permissionName])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Temporarily grants a user super-admin privileges for the current request.
     * Useful for testing or development scenarios.
     *
     * @param User $user The user to grant super-admin privileges to.
     */
    public function actAsSuperAdmin(User $user): void
    {
        $this->permissionCache[$user->id] = [
            'contexts' => [
                self::SYSTEM_CONTEXT_ID => [
                    'roles' => [
                        0 => 'super-admin',
                    ],
                    'permissions' => [],
                ],
            ],
        ];
    }

    /**
     * Checks if a user has the 'super-admin' role in the system context.
     * This check is highly optimized using the in-request cache.
     */
    public function isSuperAdmin(User $user): bool
    {
        if (!isset($this->permissionCache[$user->id])) {
            $this->loadAndCacheUserPermissions($user);
        }

        return isset($this->permissionCache[$user->id]['contexts'][self::SYSTEM_CONTEXT_ID]['roles'])
            && in_array('super-admin', $this->permissionCache[$user->id]['contexts'][self::SYSTEM_CONTEXT_ID]['roles'], true);
    }

    /**
     * Check if a user has a specific role in a given context, including parent contexts.
     *
     * @param User $user The user to check.
     * @param string $roleName The name of the role.
     * @param \Core\ORM\Model|Context|null $context The context.
     */
    public function hasRole(User $user, string $roleName, $context = null): bool
    {
        // Một super-admin ngầm định có tất cả các vai trò. Đây là một bước kiểm tra nhanh.
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if (!isset($this->permissionCache[$user->id])) {
            $this->loadAndCacheUserPermissions($user);
        }

        $context = $this->resolveContext($context);
        $contextIds = $this->getContextHierarchyIds($context);

        $userContexts = $this->permissionCache[$user->id]['contexts'] ?? [];

        foreach ($contextIds as $contextId) {
            $rolesInContext = $userContexts[$contextId]['roles'] ?? [];
            if (in_array($roleName, $rolesInContext, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Call the corresponding method on a policy if it is registered for the given context.
     */
    protected function callPolicyMethod(User $user, string $permission, $context): ?bool
    {
        // 1. Resolve the model class from the context.
        $modelClass = $this->getClassForPolicy($context);
        if (!$modelClass) {
            return null;
        }

        // 2. Find the registered policy for this model class.
        $policyClass = $this->policies[$modelClass] ?? null;
        if (!$policyClass) {
            return null;
        }

        // 3. Instantiate the policy.
        $policyInstance = $this->app->make($policyClass);

        // 4. Check the 'before' method on the policy, which can intercept any check.
        if (method_exists($policyInstance, 'before')) {
            // The 'before' method receives the user and the full ability string.
            $beforeResult = $this->app->call([$policyInstance, 'before'], [$user, $permission]);
            if (!is_null($beforeResult)) {
                return $beforeResult;
            }
        }

        // 5. Determine the specific ability method to call from the permission string.
        $methodName = $this->getPolicyMethodName($permission);

        // 6. Check if the specific ability method exists on the policy.
        if (!method_exists($policyInstance, $methodName)) {
            return null;
        }

        // 7. Prepare parameters and call the ability method.
        // The convention is that the User is the first parameter, and the model instance
        // (if provided as an object) is the second.
        $parameters = is_object($context) ? [$user, $context] : [$user];
        $result = $this->app->call([$policyInstance, $methodName], $parameters);

        // 8. Process the result, handling both boolean and Response objects.
        return $this->processPolicyResult($result);
    }

    /**
     * Get the class name for a policy check from a given context.
     */
    private function getClassForPolicy($context): ?string
    {
        if ($context instanceof Model) {
            return get_class($context);
        }

        if (is_string($context) && class_exists($context) && is_subclass_of($context, Model::class)) {
            return $context;
        }

        return null;
    }

    /**
     * Get the policy method name from a permission string (e.g., 'post:update' -> 'update').
     */
    private function getPolicyMethodName(string $permission): string
    {
        $parts = explode(':', $permission);
        return end($parts);
    }

    /**
     * Process the result from a policy method call.
     * @throws AuthorizationException
     */
    private function processPolicyResult($result): bool
    {
        if ($result instanceof Response) {
            if (!$result->allowed()) {
                throw new AuthorizationException($result->message() ?? 'This action is unauthorized.', 403);
            }
            return true;
        }

        return (bool) $result;
    }

    /**
     * Resolves a model, object, or null into a concrete Context instance.
     * If a context for a model does not exist, it will be created automatically.
     */
    public function resolveContext(mixed $context): Context
    {
        if ($context === null) {
            if ($this->systemContext === null) {
                /** @var Context $systemContext */
                $systemContext = Context::findOrFail(self::SYSTEM_CONTEXT_ID);
                $this->systemContext = $systemContext;
            }
            return $this->systemContext;
        }

        if ($context instanceof Context) {
            return $context;
        }

        if ($context instanceof \Core\ORM\Model) {
            $level = strtolower(basename(str_replace('\\', '/', get_class($context))));
            $instanceId = $context->getKey();
            $cacheKey = "{$level}:{$instanceId}";

            if (isset($this->resolvedContextCache[$cacheKey])) {
                return $this->resolvedContextCache[$cacheKey];
            }

            $attributes = ['context_level' => $level, 'instance_id' => $instanceId];

            /** @var Context $foundOrNewContext */
            $foundOrNewContext = Context::firstOrNew($attributes);

            if (!$foundOrNewContext->exists) {
                $parentContext = $this->resolveParentContext($context);

                $foundOrNewContext->fill([
                    'parent_id'     => $parentContext->id,
                    'depth'         => $parentContext->depth + 1,
                    'path'          => $parentContext->path,
                ]);
                $foundOrNewContext->save(); // First save to get an ID

                // Update path with its own ID and save again
                $foundOrNewContext->path .= $foundOrNewContext->id . '/';
                $foundOrNewContext->save();
            }

            // 3d. Cache and return
            return $this->resolvedContextCache[$cacheKey] = $foundOrNewContext;
        }

        // 4. If it's not a supported type, throw an exception.
        throw new \InvalidArgumentException('Unsupported context type provided to resolveContext.');
    }

    /**
     * Helper method to get a context directly by its level and instance ID.
     * Useful for controllers that don't have a full model instance.
     */
    public function resolveContextByLevelAndId(string $level, int $instanceId): Context
    {
        $existingContext = $this->findContextByLevelAndId($level, $instanceId);

        if ($existingContext) {
            return $existingContext;
        }

        throw new \InvalidArgumentException("Context for level '{$level}' with ID '{$instanceId}' does not exist.");
    }

    /**
     * Helper to find a context by level and instance ID.
     */
    private function findContextByLevelAndId(string $level, int $instanceId): ?Context
    {
        /** @var Context|null */
        return Context::where('context_level', '=', $level)->where('instance_id', '=', $instanceId)->first();
    }
    /**
     * Determines the parent context for a given model.
     * Uses the `#[ParentContext]` attribute for maximum flexibility.
     */
    private function resolveParentContext(\Core\ORM\Model $model): Context
    {
        $className = get_class($model);

        if (!array_key_exists($className, self::$reflectionCache)) {
            self::$reflectionCache[$className] = null;
            $reflector = new \ReflectionClass($className);

            foreach ($reflector->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if (!empty($method->getAttributes(ParentContext::class))) {
                    self::$reflectionCache[$className] = $method;
                    break;
                }
            }
        }

        /** @var ?\ReflectionMethod $parentMethod */
        $parentMethod = self::$reflectionCache[$className];

        if ($parentMethod) {
            $parentModel = $parentMethod->invoke($model);
            if ($parentModel instanceof \Core\ORM\Model) {
                return $this->resolveContext($parentModel);
            }
        }

        // If no specific parent is found, default to the system context.
        return $this->resolveContext(null);
    }

    private function getContextHierarchyIds(Context $context): array
    {
        // Performance optimization: Cache context hierarchy parsing
        $contextId = $context->id;
        
        if (!isset($this->contextHierarchyCache[$contextId])) {
            // Use the Materialized Path to get all IDs in the hierarchy.
            // e.g., a path of '1/5/12/' will return [1, 5, 12].
            $this->contextHierarchyCache[$contextId] = explode('/', rtrim($context->path, '/'));
        }
        
        return $this->contextHierarchyCache[$contextId];
    }

    /**
     * Loads and caches all permissions for a user.
     * This is a core optimization to prevent N+1 query problems.
     */
    private function loadAndCacheUserPermissions(User $user): void
    {
        $cacheKey = "acl:all_perms:{$user->id}";
        $l1Key = "acl:l1:{$user->id}";

        if (function_exists('apcu_fetch')) {
            $success = false;
            $l1Data = apcu_fetch($l1Key, $success);
            if ($success && is_array($l1Data)) {
                $this->permissionCache[$user->id] = $l1Data;
                return;
            }
        }

        if ($this->cacheStore) {
            $cachedPermissions = $this->cacheStore->get($cacheKey);
            if ($cachedPermissions) {
                $decoded = json_decode($cachedPermissions, true);
                if (is_array($decoded)) {
                    $this->permissionCache[$user->id] = $decoded;
                    
                    // Store in L1 cache for next time
                    if (function_exists('apcu_store')) {
                        apcu_store($l1Key, $decoded, 60); // 1 minute TTL
                    }
                    
                    return;
                }
            }
        }

        // 2. If cache is missed, use a non-blocking lock to prevent Cache Stampede.
        $lock = $this->permissionBuildMutex?->acquire($user->id);

        try {
            // This will pause the current Fiber without blocking the worker thread.
            if ($lock) {
                Future\await([$lock]);
            }

            // 2a. Double-check inside the lock in case another Fiber built the cache while we waited.
            if ($this->cacheStore) {
                $cached = $this->cacheStore->get($cacheKey);
                if ($cached) {
                    $decoded = json_decode($cached, true);
                    if (is_array($decoded)) {
                        $this->permissionCache[$user->id] = $decoded;
                        
                        // Store in L1
                        if (function_exists('apcu_store')) {
                            apcu_store($l1Key, $decoded, 60);
                        }
                        
                        return;
                    }
                }
            }

            // 2b. Build permissions from the database.
            $permissionsData = $this->buildPermissionsFromDatabase($user);

            // 2c. Store in the persistent cache (L2).
            if ($this->cacheStore) {
                $ttl = $this->app->make('config')->get('auth.cache.permissions_ttl', 3600);
                $this->cacheStore->set($cacheKey, json_encode($permissionsData), $ttl);
            }
            
            // 2d. Store in L1 cache (APCu).
            if (function_exists('apcu_store')) {
                apcu_store($l1Key, $permissionsData, 60);
            }
        } finally {
            // 2e. Always release the lock.
            $lock?->release();
        }

        // 3. Populate the in-request cache.
        $this->permissionCache[$user->id] = $permissionsData;
    }

    /**
     * Build permissions from database with optimized query.
     * 
     * PERFORMANCE OPTIMIZATIONS:
     * - Cache role-permission matrix to avoid repeated joins
     * - Use optimized query with proper indexes
     * - Pre-index permissions for O(1) lookup
     */
    private function buildPermissionsFromDatabase(User $user): array
    {
        // Performance optimization: Load role-permission matrix once (shared across all users)
        $rolePermissionMatrix = $this->getRolePermissionMatrix();
        
        // Get user's role assignments (optimized query with indexes)
        $roleAssignments = RoleAssignment::query()
            ->where('user_id', '=', $user->id)
            ->select('context_id', 'role_id')
            ->get();

        $permissionsByContext = [];
        
        foreach ($roleAssignments as $assignment) {
            $contextId = $assignment->context_id ?? self::SYSTEM_CONTEXT_ID;
            $roleId = $assignment->role_id;

            if (!isset($permissionsByContext[$contextId])) {
                $permissionsByContext[$contextId] = ['roles' => [], 'permissions' => []];
            }

            // Get role name (cache this if needed)
            $role = $this->getRoleById($roleId);
            if ($role) {
                $permissionsByContext[$contextId]['roles'][$roleId] = $role->name;
                
                // Add all permissions for this role (from cached matrix)
                if (isset($rolePermissionMatrix[$roleId])) {
                    foreach ($rolePermissionMatrix[$roleId] as $permissionName) {
                        $permissionsByContext[$contextId]['permissions'][$permissionName] = true;
                    }
                }
            }
        }

        return ['contexts' => $permissionsByContext];
    }
    
    /**
     * Get role-permission matrix (cached to avoid repeated joins).
     * This matrix is shared across all users and contexts.
     * 
     * @return array [roleId => [permissionName1, permissionName2, ...]]
     */
    private function getRolePermissionMatrix(): array
    {
        // Static cache for the request lifecycle
        if (self::$rolePermissionMatrix !== null) {
            return self::$rolePermissionMatrix;
        }
        
        // Check persistent cache
        $cacheKey = 'acl:role_permission_matrix';
        if ($this->cacheStore) {
            $cached = $this->cacheStore->get($cacheKey);
            if ($cached) {
                $decoded = json_decode($cached, true);
                if (is_array($decoded)) {
                    return self::$rolePermissionMatrix = $decoded;
                }
            }
        }
        
        // Build matrix from database (one-time query)
        $pdo = $this->app->make(\PDO::class);
        $stmt = $pdo->prepare("
            SELECT pr.role_id, p.name as permission_name
            FROM permission_role pr
            INNER JOIN permissions p ON pr.permission_id = p.id
        ");
        $stmt->execute();
        $results = $stmt->fetchAll(\PDO::FETCH_OBJ);
        
        $matrix = [];
        foreach ($results as $row) {
            $roleId = $row->role_id;
            if (!isset($matrix[$roleId])) {
                $matrix[$roleId] = [];
            }
            $matrix[$roleId][] = $row->permission_name;
        }
        
        // Cache for 1 hour (role-permission relationships don't change often)
        if ($this->cacheStore) {
            $this->cacheStore->set($cacheKey, json_encode($matrix), 3600);
        }
        
        return self::$rolePermissionMatrix = $matrix;
    }
    
    /**
     * Get role by ID with simple caching.
     */
    private function getRoleById(int $roleId): ?\Modules\User\Infrastructure\Models\Role
    {
        static $roleCache = [];
        
        if (!isset($roleCache[$roleId])) {
            $roleCache[$roleId] = \Modules\User\Infrastructure\Models\Role::find($roleId);
        }
        
        return $roleCache[$roleId];
    }

    /**
     * Xóa cache quyền cho một người dùng cụ thể.
     * Bao gồm cả cache bền vững (Redis/file) và cache trong request.
     * 
     * PERFORMANCE OPTIMIZATION: Clear all cache levels (L1, L2, in-memory)
     *
     * @param int $userId ID của người dùng cần xóa cache.
     */
    public function flushCacheForUser(int $userId): void
    {
        // L1: Clear APCu cache
        if (function_exists('apcu_delete')) {
            apcu_delete("acl:l1:{$userId}");
        }
        
        // L2: Clear persistent cache (Redis/file)
        if ($this->cacheStore) {
            $this->cacheStore->delete("acl:all_perms:{$userId}");
        }

        // In-memory: Clear request cache
        unset($this->permissionCache[$userId]);
        
        // Clear context hierarchy cache for this user's contexts
        // (Note: This is a simple approach, could be more selective)
        $this->contextHierarchyCache = [];
    }
    
    /**
     * Flush role-permission matrix cache.
     * Should be called when role permissions are changed.
     */
    public function flushRolePermissionMatrix(): void
    {
        self::$rolePermissionMatrix = null;
        
        if ($this->cacheStore) {
            $this->cacheStore->delete('acl:role_permission_matrix');
        }
    }
    
    /**
     * Batch check multiple permissions for a user (optimized).
     * 
     * @param User $user
     * @param array $permissions Array of permission names
     * @param mixed $context
     * @return array ['permission1' => true, 'permission2' => false, ...]
     */
    public function checkBatch(User $user, array $permissions, $context = null): array
    {
        // Load permissions once for all checks
        if (!isset($this->permissionCache[$user->id])) {
            $this->loadAndCacheUserPermissions($user);
        }
        
        $context = $this->resolveContext($context);
        $contextIds = $this->getContextHierarchyIds($context);
        $userContexts = $this->permissionCache[$user->id]['contexts'] ?? [];
        
        $results = [];
        
        // Pre-build permission lookup map for O(1) access
        $permissionMap = [];
        foreach ($contextIds as $contextId) {
            if (isset($userContexts[$contextId]['permissions'])) {
                foreach ($userContexts[$contextId]['permissions'] as $perm => $value) {
                    if (!isset($permissionMap[$perm])) {
                        $permissionMap[$perm] = true;
                    }
                }
            }
        }
        
        // Check all permissions using the map
        foreach ($permissions as $permission) {
            $results[$permission] = isset($permissionMap[$permission]);
        }
        
        return $results;
    }
}
