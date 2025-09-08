<?php

namespace Modules\User\Infrastructure\Models;

use Core\Contracts\Auth\Authenticatable;
use Core\ORM\Model;
use Core\ORM\Relations\HasMany;
use Modules\User\Domain\Services\AccessControlService;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property-read \DateTimeInterface|null $created_at
 * @property-read \DateTimeInterface|null $updated_at
 */
class User extends Model implements Authenticatable
{
    protected static string $table = 'users';

    protected array $fillable = ['name', 'email', 'password'];

    protected array $hidden = ['password', 'remember_token'];

    protected static function booted(): void
    {
        static::deleted(function (self $user) {
            dispatch(new \Modules\User\Domain\Events\UserDeleted($user->id));
        });
    }

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

    public function getRememberToken(): ?string
    {
        return $this->{$this->getRememberTokenName()};
    }

    public function setRememberToken($value): void
    {
        $this->{$this->getRememberTokenName()} = $value;
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
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
        // Delegate the role check to the optimized AccessControlService.
        // This leverages the central caching mechanism and respects context hierarchy.
        return app(AccessControlService::class)->hasRole($this, $roleName, $context);
    }

    /**
     * Check if the user is a super-admin.
     * This is a convenient shortcut that delegates the check to the AccessControlService.
     *
     * @return bool
     */
    public function isSuperAdmin(): bool
    {
        return app(AccessControlService::class)->isSuperAdmin($this);
    }
}
