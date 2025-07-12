<?php

namespace Core;

use Spatie\Permission\Models\Permission;

class PermissionRegistrar
{
    public static function register(string $path): void
    {
        if (file_exists($path)) {
            $permissions = require $path;
            foreach ($permissions as $perm) {
                Permission::findOrCreate($perm);
            }
        }
    }
}
