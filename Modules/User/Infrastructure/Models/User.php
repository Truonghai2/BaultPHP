<?php

namespace Modules\User\Infrastructure\Models;

use Core\Contracts\Auth\Authenticatable;
use Core\ORM\Model;
use Core\ORM\Relations\BelongsToMany;
use Core\ORM\Relations\HasMany;
use Modules\User\Application\Services\AccessControlService;

class User extends Model implements Authenticatable
{
    protected static string $table = 'users';
    
    protected array $fillable = ['name', 'email', 'password'];

    private ?AccessControlService $acl = null;

    public function getAuthIdentifier()
    {
        return $this->getKey();
    }

    public function getAuthIdentifierName(): string
    {
        return $this->getKeyName();
    }

    public function getAuthPassword(): string
    {
        return $this->password;
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * A user has many role assignments in different contexts.
     */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    /**
     * Check if the user has a specific permission, potentially in a given context.
     *
     * @param string $permissionName The name of the permission (e.g., 'post.edit').
     * @param Model|null $context The model instance representing the context (e.g., a Post object).
     * @return bool
     */
    public function can(string $permissionName, $context = null): bool
    {
        // Lấy service từ container thay vì tạo mới, tuân thủ Dependency Injection.
        return app(AccessControlService::class)->check($this, $permissionName, $context);
    }

    /**
     * Check if the user has a specific role in a given context.
     *
     * @param string $roleName The name of the role.
     * @param Model|Context|null $context The context to check within. Defaults to system context.
     * @return bool
     */
    public function hasRole(string $roleName, $context = null): bool
    {
        $role = Role::where('name', $roleName)->first();
        if (!$role) {
            return false;
        }

        // Nếu không có context, kiểm tra ở cấp hệ thống
        $contextId = $context ? app(AccessControlService::class)->resolveContext($context)->id : 1;

        return $this->roleAssignments()
            ->where('role_id', $role->id)
            ->where('context_id', $contextId)
            ->exists();
    }
}