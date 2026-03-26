<?php

declare(strict_types=1);

namespace Core\Tenancy;

use Core\ORM\Model;

/**
 * Per-tenant module enable/disable and optional config.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $module_name
 * @property bool $enabled
 * @property array|null $config
 * @property string $created_at
 * @property string $updated_at
 */
class TenantModule extends Model
{
    protected static string $table = 'tenant_modules';

    protected array $fillable = ['tenant_id', 'module_name', 'enabled', 'config'];

    protected array $casts = ['enabled' => 'boolean', 'config' => 'array'];

    public function tenant(): \Core\ORM\Relations\BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
