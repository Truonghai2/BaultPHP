<?php

namespace Modules\Cms\Infrastructure\Models;

use Core\Audit\Traits\Auditable;
use Core\ORM\Model;
use Core\ORM\Relations\HasMany;

/**
 * Block Region Model
 *
 * Đại diện cho một vùng (region) có thể chứa blocks
 * Ví dụ: sidebar-left, sidebar-right, footer...
 *
 * @property int $id
 * @property string $name
 * @property string $title
 * @property string|null $description
 * @property int $max_blocks
 * @property bool $is_active
 * @property-read \Core\Support\Collection<int, BlockInstance> $blocks
 */
class BlockRegion extends Model
{
    use Auditable;

    protected static string $table = 'block_regions';

    protected array $fillable = [
        'name',
        'title',
        'description',
        'max_blocks',
        'is_active',
    ];

    protected $casts = [
        'max_blocks' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Lấy tất cả block instances trong region này
     */
    public function blocks(): HasMany
    {
        $relation = $this->hasMany(BlockInstance::class, 'region_id');
        $relation->getQuery()
            ->where('visible', true)
            ->orderBy('weight', 'asc');
        return $relation;
    }

    /**
     * Lấy tất cả blocks (bao gồm ẩn)
     */
    public function allBlocks(): HasMany
    {
        $relation = $this->hasMany(BlockInstance::class, 'region_id');
        $relation->getQuery()->orderBy('weight', 'asc');
        return $relation;
    }

    /**
     * Check xem region đã đầy chưa
     */
    public function isFull(): bool
    {
        return $this->blocks()->count() >= $this->max_blocks;
    }

    /**
     * Thêm block vào region
     */
    public function addBlock(BlockType $blockType, array $attributes = []): BlockInstance
    {
        if ($this->isFull()) {
            throw new \RuntimeException("Region {$this->name} is full (max: {$this->max_blocks})");
        }

        // Tính weight mới (thêm vào cuối)
        $maxWeight = $this->allBlocks()->max('weight') ?? 0;

        $instance = new BlockInstance(array_merge([
            'block_type_id' => $blockType->id,
            'region_id' => $this->id,
            'title' => $blockType->title,
            'config' => $blockType->default_config,
            'weight' => $maxWeight + 1,
        ], $attributes));

        $instance->save();

        return $instance;
    }

    /**
     * Scope: Chỉ lấy active regions
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
