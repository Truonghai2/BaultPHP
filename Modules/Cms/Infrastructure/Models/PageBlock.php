<?php

namespace Modules\Cms\Infrastructure\Models;

use Core\ORM\Model;
use Core\ORM\Relations\BelongsTo;
use Modules\User\Infrastructure\Models\User;
use Core\Audit\Traits\Auditable;

/**
 * PageBlock Model
 * 
 * @property int $id
 * @property int $page_id
 * @property int $block_type_id
 * @property string $region
 * @property string|null $content
 * @property int $sort_order
 * @property bool $visible
 * @property array|null $visibility_rules
 * @property array|null $allowed_roles
 * @property int|null $created_by
 * @property-read Page $page
 * @property-read BlockType $blockType
 * @property-read User|null $creator
 * 
 * Note: title and config are accessed via blockType relationship:
 * - $block->blockType->title
 * - $block->blockType->default_config
 */
class PageBlock extends Model
{
    use Auditable;

    protected static string $table = 'page_blocks';

    protected array $fillable = [
        'page_id',
        'block_type_id',
        'region',
        'content',
        'sort_order',
        'visible',
        'visibility_rules',
        'allowed_roles',
        'created_by',
    ];

    protected $casts = [
        'visibility_rules' => 'array',
        'allowed_roles' => 'array',
        'sort_order' => 'integer',
        'visible' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Page relationship
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * Block type relationship (NEW - Direct connection)
     */
    public function blockType(): BelongsTo
    {
        return $this->belongsTo(BlockType::class, 'block_type_id', 'id');
    }

    /**
     * Creator relationship
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get block title (from block type)
     */
    public function getTitle(): string
    {
        return $this->blockType?->title ?? 'Untitled Block';
    }

    /**
     * Get block configuration (from block type)
     * 
     * Uses static caching for performance (except in debug mode)
     * 
     * @return array<string, mixed> Configuration array
     */
    public function getConfig(): array
    {
        // In debug mode, always get fresh config
        if (config('app.debug', false)) {
            return $this->getFreshConfig();
        }

        // In production, use static cache
        static $configCache = [];
        
        if (!$this->blockType) {
            return [];
        }
        
        $cacheKey = $this->block_type_id;
        
        if (!isset($configCache[$cacheKey])) {
            $configCache[$cacheKey] = $this->getFreshConfig();
        }
        
        return $configCache[$cacheKey];
    }

    /**
     * Get fresh config without caching
     * 
     * @return array<string, mixed>
     */
    private function getFreshConfig(): array
    {
        if (!$this->blockType) {
            return [];
        }

        $config = $this->blockType->default_config;
        
        if (is_string($config)) {
            $decoded = json_decode($config, true);
            return is_array($decoded) ? $decoded : [];
        }
        
        return is_array($config) ? $config : [];
    }

    /**
     * Get a specific config value
     * 
     * @param string $key Config key
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public function getConfigValue(string $key, $default = null)
    {
        $config = $this->getConfig();
        return $config[$key] ?? $default;
    }

    /**
     * Render this block (legacy - backward compatibility)
     */
    public function render(?User $user = null): string
    {
        return $this->renderOptimized($user, null);
    }

    /**
     * Render this block (optimized version with registry)
     * 
     * @param User|null $user Current user for visibility checks
     * @param \Modules\Cms\Domain\Services\BlockClassRegistry|null $registry Block class registry (optional)
     * @param array|null $renderContext Additional context from the renderer (e.g., preloaded data)
     * @return string Rendered HTML
     */
    public function renderOptimized(?User $user = null, ?\Modules\Cms\Domain\Services\BlockClassRegistry $registry = null, ?array $renderContext = null): string
    {
        if (!$this->isVisibleTo($user)) {
            return '';
        }

        if (!$this->blockType) {
            return "<!-- Block type not found for PageBlock #{$this->id} -->";
        }

        $blockClass = $this->blockType->class;
        
        if (!$blockClass) {
            return "<!-- No block class defined for BlockType #{$this->block_type_id} -->";
        }

        if ($registry) {
            $block = $registry->getInstance($blockClass);
            if (!$block) {
                return "<!-- Block class not found or invalid: {$blockClass} -->";
            }
        } else {
            if (!class_exists($blockClass)) {
                return "<!-- Block class not found: {$blockClass} -->";
            }
            $block = new $blockClass();
        }
        
        if (method_exists($block, 'render')) {
            $context = $renderContext ?? [];
            $context['block_info'] = [
                'block_id' => $this->id,
                'page_id' => $this->page_id,
                'region' => $this->region,
                'content' => $this->content,
            ];

            return $block->render($this->getConfig(), $context);
        }

        return $this->content ?? '';
    }

    /**
     * Check if block is visible to user
     */
    public function isVisibleTo(?User $user = null): bool
    {
        if (!$this->visible) {
            return false;
        }

        if ($this->allowed_roles && count($this->allowed_roles) > 0) {
            if (!$user) {
                return in_array('guest', $this->allowed_roles);
            }
            
            $userRoles = method_exists($user, 'getRoles') ? $user->getRoles() : [];
            if (count(array_intersect($userRoles, $this->allowed_roles)) === 0) {
                return false;
            }
        }

        if ($this->visibility_rules && count($this->visibility_rules) > 0) {
            return $this->evaluateVisibilityRules($user);
        }

        return true;
    }

    /**
     * Evaluate visibility rules
     */
    protected function evaluateVisibilityRules(?User $user): bool
    {
        return true;
    }

    /**
     * Move block up (decrease order)
     */
    public function moveUp(): void
    {
        if ($this->sort_order > 0) {
            $this->sort_order--;
            $this->save();
        }
    }

    /**
     * Move block down (increase order)
     */
    public function moveDown(): void
    {
        $this->sort_order++;
        $this->save();
    }

    /**
     * Toggle visibility
     */
    public function toggleVisibility(): void
    {
        $this->visible = !$this->visible;
        $this->save();
    }


    /**
     * Duplicate this block to another page
     */
    public function duplicateTo(Page $targetPage): self
    {
        $newBlock = $this->replicate();
        $newBlock->page_id = $targetPage->id;
        $newBlock->save();
        
        return $newBlock;
    }

    /**
     * Scope to filter visible blocks only
     */
    public function scopeVisible($query)
    {
        return $query->where('visible', true);
    }

    /**
     * Scope to filter blocks by region
     */
    public function scopeInRegion($query, string $region)
    {
        return $query->where('region', $region);
    }

    /**
     * Scope to order blocks by sort_order field
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc');
    }
}
