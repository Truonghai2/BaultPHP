<?php

namespace Modules\User\Infrastructure\Models;

use Core\ORM\Model;

/**
 * @property int $id
 * @property string $context_level
 * @property int $instance_id
 * @property int|null $parent_id
 * @property int $depth
 * @property string $path
 */
class Context extends Model
{
    public const LEVEL_SYSTEM = 'system';

    protected static string $table = 'contexts';
    protected array $fillable = ['context_level', 'instance_id', 'path', 'depth'];
}
