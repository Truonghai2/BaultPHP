<?php

namespace Core\Module;

use Core\ORM\Model;

/**
 * Represents a module in the application.
 *
 * This model is used to store module information in the database,
 * such as its name, version, and enabled status. It connects the concept
 * of a module to the database, allowing it to be queried and managed
 * through the ORM.
 */
class Module extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected static string $table = 'modules';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected array $fillable = [
        'name',
        'version',
        'description',
        'enabled',
    ];

    protected array $casts = [
        'enabled' => 'boolean',
    ];
}