<?php

declare(strict_types=1);

namespace Modules\Cms\Infrastructure\Models;

use Core\ORM\Model;
use Core\Audit\Traits\Auditable;

/**
 * Page Template Model
 * 
 * Represents pre-configured page templates with blocks
 * 
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $thumbnail
 * @property string $category
 * @property array $blocks_config
 * @property array|null $default_seo
 * @property bool $is_active
 * @property bool $is_system
 * @property int $sort_order
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 */
class PageTemplate extends Model
{
    use Auditable;
    
    protected static string $table = 'page_templates';

    protected array $fillable = [
        'name',
        'description',
        'thumbnail',
        'category',
        'blocks_config',
        'default_seo',
        'is_active',
        'is_system',
        'sort_order',
    ];

    protected $casts = [
        'blocks_config' => 'array',
        'default_seo' => 'array',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get templates by category
     */
    public static function byCategory(string $category)
    {
        return static::where('category', $category)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Get all active templates
     */
    public static function active()
    {
        return static::where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }
}

