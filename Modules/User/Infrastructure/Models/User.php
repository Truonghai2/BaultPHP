<?php

namespace Modules\User\Infrastructure\Models;

use Core\Audit\Traits\Auditable;
use Core\Contracts\Auth\Authenticatable;
use Core\Database\Concerns\HasFactory;
use Core\ORM\Model;
use Core\ORM\Relations\BelongsToMany;
use Core\ORM\Relations\HasMany;
use Modules\User\Domain\Services\AccessControlService;

/**
 * User Model
 * 
 * Represents a user in the system with authentication and authorization capabilities.
 * Auto-logs all changes for security and compliance.
 * 
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property-read \DateTimeInterface|null $created_at
 * @property-read \DateTimeInterface|null $updated_at
 * @property-read \Core\Support\Collection<int, \Modules\User\Infrastructure\Models\Role> $roles
 * @property-read \Core\Support\Collection<int, \Modules\User\Infrastructure\Models\RoleAssignment> $roleAssignments
 */
class User extends Model implements Authenticatable
{
    use Auditable;
    use HasFactory;

    protected static string $table = 'users';

    protected array $fillable = ['name', 'email', 'password'];

    protected array $hidden = ['password', 'remember_token'];

    /**
     * Define which events should be audited.
     */
    protected array $auditableEvents = ['created', 'updated', 'deleted'];

    /**
     * Define which attributes should be tracked in audit logs.
     * Password changes are tracked but value is hidden.
     */
    protected array $auditableAttributes = ['name', 'email'];

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
     * Get all roles assigned to the user across all contexts.
     * This relationship returns unique roles regardless of context.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_assignments')
            ->withPivot(['context_id'])
            ->withTimestamps();
    }

    /**
     * Get all unique role names for the user across all contexts.
     * Returns an array of role names like ['super-admin', 'editor', 'viewer']
     * 
     * @return array<string>
     */
    public function getRoles(): array
    {
        if (isset($this->relations['roles'])) {
            return $this->relations['roles']->pluck('name')->unique()->values()->toArray();
        }

        $roles = $this->roles()->get();
        return $roles->pluck('name')->unique()->values()->toArray();
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
