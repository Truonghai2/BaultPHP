<?php

namespace Modules\User\Infrastructure\Models;

use Core\ORM\Model;

class Context extends Model
{
    const LEVEL_SYSTEM = 'system';

    protected static string $table = 'contexts';
    protected array $fillable = ['context_level', 'instance_id', 'path', 'depth'];
}