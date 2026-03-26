<?php

declare(strict_types=1);

namespace Core\Tenancy;

use Core\ORM\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property array|null $config
 * @property string $created_at
 * @property string $updated_at
 */
class Tenant extends Model
{
    protected static string $table = 'tenants';

    protected array $fillable = ['name', 'slug', 'config'];

    protected array $casts = ['config' => 'array'];

    public function modules(): \Core\ORM\Relations\Relation
    {
        return $this->hasMany(TenantModule::class, 'tenant_id');
    }
}
