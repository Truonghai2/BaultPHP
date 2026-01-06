<?php

namespace Modules\Cms\Infrastructure\Models;

use Core\ORM\Model;
use Core\ORM\Relations\HasMany;
use Core\Audit\Traits\Auditable;

/**
 * Block Type Model
 * 
 * Đại diện cho một loại block trong hệ thống (giống block plugin trong Moodle)
 * 
 * @property int $id
 * @property string $name
 * @property string $title
 * @property string|null $description
 * @property string $class
 * @property string $category
 * @property string|null $icon
 * @property array|null $default_config
 * @property bool $configurable
 * @property bool $is_active
 * @property int $version
 * @property-read \Core\Support\Collection<int, BlockInstance> $instances
 */
class BlockType extends Model
{
    use Auditable;  

    protected static string $table = 'block_types';

    protected array $fillable = [
        'name',
        'title',
        'description',
        'class',
        'category',
        'icon',
        'default_config',
        'configurable',
        'is_active',
        'version',
    ];

    protected $casts = [
        'default_config' => 'array',
        'configurable' => 'boolean',
        'is_active' => 'boolean',
        'version' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Lấy tất cả instances của block type này
     */
    public function instances(): HasMany
    {
        return $this->hasMany(BlockInstance::class);
    }

    /**
     * Tạo instance mới từ block type này
     */
    public function createInstance(array $attributes = []): BlockInstance
    {
        $instance = new BlockInstance(array_merge([
            'block_type_id' => $this->id,
            'title' => $this->title,
            'config' => $this->default_config,
        ], $attributes));

        $instance->save();

        return $instance;
    }

    /**
     * Instantiate block class
     */
    public function instantiate(): mixed
    {
        if (!class_exists($this->class)) {
            throw new \RuntimeException("Block class {$this->class} not found");
        }

        return new $this->class();
    }

    /**
     * Scope: Chỉ lấy active block types
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Lọc theo category
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}

