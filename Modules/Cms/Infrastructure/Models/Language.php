<?php

declare(strict_types=1);

namespace Modules\Cms\Infrastructure\Models;

use Core\Audit\Traits\Auditable;
use Core\ORM\Model;

/**
 * Language Model
 *
 * Represents available languages for multi-language support
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $native_name
 * @property bool $is_default
 * @property bool $is_active
 * @property string $direction
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 */
class Language extends Model
{
    use Auditable;

    protected static string $table = 'languages';

    protected array $fillable = [
        'code',
        'name',
        'native_name',
        'is_default',
        'is_active',
        'direction',
        'sort_order',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get default language
     */
    public static function getDefault(): ?Language
    {
        return static::where('is_default', true)->first();
    }

    /**
     * Get all active languages
     */
    public static function active()
    {
        return static::where('is_active', true)->get();
    }

    /**
     * Get language by code
     */
    public static function findByCode(string $code): ?Language
    {
        return static::where('code', $code)->first();
    }
}
