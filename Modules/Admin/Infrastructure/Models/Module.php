<?php

namespace Modules\Admin\Infrastructure\Models;

use Core\ORM\Model;

/**
 * Represents a module in the system, mapping to the 'modules' table.
 *
 * @property int $id
 * @property string $name
 * @property string $version
 * @property bool $enabled
 * @property string $status
 * @property string|null $description
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Module extends Model
{
    protected static string $table = 'modules';

    protected $fillable = ['name', 'version', 'enabled', 'status', 'description'];

    protected $casts = ['enabled' => 'boolean'];
}
