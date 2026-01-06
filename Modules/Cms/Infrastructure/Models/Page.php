<?php

namespace Modules\Cms\Infrastructure\Models;

use Core\Audit\Traits\Auditable;
use Core\ORM\Model;
use Core\ORM\Relations\HasMany;

/**
 * Page Model
 *
 * Represents a page in the CMS with its associated blocks
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int $user_id
 * @property-read \Core\Support\Collection<int, \Modules\Cms\Infrastructure\Models\BlockInstance> $blockInstances
 * @property-read \Core\Support\Collection<int, \Modules\Cms\Infrastructure\Models\PageBlock> $blocks
 * @deprecated Use blockInstances() instead of blocks()
 */
class Page extends Model
{
    use Auditable;

    protected static string $table = 'pages';

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    protected array $fillable = [
        'name',
        'slug',
        'user_id',
        'status',
        'published_at',
        'scheduled_publish_at',
        'language_code',
        'translation_group_id',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'og_image',
        'og_type',
        'canonical_url',
        'robots',
        'schema_data',
    ];

    protected $casts = [
        'status' => 'string',
        'published_at' => 'datetime',
        'scheduled_publish_at' => 'datetime',
        'schema_data' => 'array',
        'translation_group_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get all blocks for this page (Direct connection to block_types)
     *
     * Returns page_blocks ordered by region and order
     *
     * @return HasMany
     */
    public function blocks(): HasMany
    {
        $relation = $this->hasMany(PageBlock::class);
        $relation->getQuery()->orderBy('sort_order');
        return $relation;
    }

    /**
     * Get blocks for a specific region
     *
     * PERFORMANCE OPTIMIZATION: Query result caching with short TTL
     *
     * @param string $region Region name (e.g., 'hero', 'content', 'sidebar')
     * @param \Modules\User\Infrastructure\Models\User|null $user Optional user for visibility pre-filtering
     * @return \Core\Support\Collection<int, PageBlock>
     */
    public function blocksInRegion(string $region, ?\Modules\User\Infrastructure\Models\User $user = null): \Core\Support\Collection
    {
        // Performance optimization: Cache query results with short TTL
        $cacheKey = "page_{$this->id}_blocks_region_{$region}";

        // Include user context in cache key if provided (for role-based filtering)
        if ($user) {
            $userRoles = method_exists($user, 'getRoles') ? $user->getRoles() : [];
            $roleHash = md5(serialize($userRoles));
            $cacheKey .= "_{$roleHash}";
        } else {
            $cacheKey .= '_guest';
        }

        // Cache for 60 seconds (short TTL to balance performance and freshness)
        return cache()->remember($cacheKey, 60, function () use ($region, $user) {
            $query = PageBlock::where('page_id', $this->id)
                ->where('region', $region)
                ->where('visible', true);

            // Performance optimization: Pre-filter by roles if user provided
            if ($user) {
                $userRoles = method_exists($user, 'getRoles') ? $user->getRoles() : [];

                // If user has roles, filter blocks that allow those roles or have no restriction
                if (!empty($userRoles)) {
                    $query->where(function ($q) use ($userRoles) {
                        $q->whereNull('allowed_roles')
                          ->orWhereJsonContains('allowed_roles', $userRoles);
                    });
                }
            } else {
                // Guest: only blocks with 'guest' role or no role restriction
                $query->where(function ($q) {
                    $q->whereNull('allowed_roles')
                      ->orWhereJsonContains('allowed_roles', 'guest');
                });
            }

            return $query->with('blockType')
                ->orderBy('sort_order')
                ->get();
        });
    }

    /**
     * Get all regions used by this page
     *
     * @return array Array of region names: ['hero', 'content', 'sidebar', 'footer']
     */
    public function getRegions(): array
    {
        $regions = PageBlock::where('page_id', $this->id)
            ->select('region')
            ->distinct()
            ->pluck('region');

        return array_values(array_unique($regions));
    }

    /**
     * LEGACY: Get block instances (for backward compatibility with global blocks)
     *
     * @return HasMany
     */
    public function blockInstances(): HasMany
    {
        $relation = $this->hasMany(BlockInstance::class, 'context_id');
        $relation->getQuery()
            ->where('context_type', 'page')
            ->orderBy('weight');
        return $relation;
    }

    /**
     * Check if page has any blocks
     *
     * @return bool
     */
    public function hasBlocks(): bool
    {
        return $this->blocks()->exists();
    }

    /**
     * Get visible blocks count
     *
     * @return int
     */
    public function visibleBlocksCount(): int
    {
        return $this->blocks()
            ->where('visible', true)
            ->count();
    }

    /**
     * Add a block to this page
     *
     * @param BlockType|int $blockType BlockType instance or ID
     * @param string $region Region name
     * @return PageBlock
     */
    public function addBlock($blockType, string $region = 'content'): PageBlock
    {
        $blockTypeId = $blockType instanceof BlockType ? $blockType->id : $blockType;

        // Get max sort_order in this region
        $maxOrder = $this->blocks()
            ->where('region', $region)
            ->max('sort_order') ?? 0;

        $pageBlock = new PageBlock([
            'page_id' => $this->id,
            'block_type_id' => $blockTypeId,
            'region' => $region,
            'sort_order' => $maxOrder + 1,
            'visible' => true,
        ]);

        $pageBlock->save();

        return $pageBlock;
    }

    /**
     * Remove a block from this page
     *
     * @param int $blockId
     * @return bool
     */
    public function removeBlock(int $blockId): bool
    {
        return $this->blocks()->where('id', $blockId)->delete() > 0;
    }

    /**
     * Duplicate all blocks to another page
     *
     * @param Page $targetPage
     * @return int Number of blocks duplicated
     */
    public function duplicateBlocksTo(Page $targetPage): int
    {
        $blocks = $this->blocks()->get();
        $count = 0;

        foreach ($blocks as $block) {
            $block->duplicateTo($targetPage);
            $count++;
        }

        return $count;
    }

    /**
     * Get revisions for this page
     */
    public function revisions(): HasMany
    {
        $relation = $this->hasMany(PageRevision::class);
        $relation->getQuery()->orderBy('revision_number', 'desc');
        return $relation;
    }

    /**
     * Check if page is published
     */
    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * Check if page is draft
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if page is archived
     */
    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    /**
     * Publish this page
     */
    public function publish(): void
    {
        $this->status = self::STATUS_PUBLISHED;
        $this->published_at = now();
        $this->save();
    }

    /**
     * Unpublish (set to draft)
     */
    public function unpublish(): void
    {
        $this->status = self::STATUS_DRAFT;
        $this->save();
    }

    /**
     * Archive this page
     */
    public function archive(): void
    {
        $this->status = self::STATUS_ARCHIVED;
        $this->save();
    }

    /**
     * Scope to get only published pages
     */
    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * Scope to get only draft pages
     */
    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Scope to filter by language
     */
    public function scopeLanguage($query, string $languageCode)
    {
        return $query->where('language_code', $languageCode);
    }

    /**
     * Get page title for SEO (with fallback)
     */
    public function getSeoTitle(): string
    {
        return $this->meta_title ?: $this->name;
    }

    /**
     * Get page description for SEO
     */
    public function getSeoDescription(): string
    {
        return $this->meta_description ?? '';
    }

    /**
     * Get translations of this page
     */
    public function translations()
    {
        if (!$this->translation_group_id) {
            return collect([]);
        }

        return static::where('translation_group_id', $this->translation_group_id)
            ->where('id', '!=', $this->id)
            ->get();
    }

    /**
     * Render blocks in a region
     *
     * @param string $region
     * @param \Modules\User\Infrastructure\Models\User|null $user
     * @return string
     */
    public function renderRegion(string $region, $user = null): string
    {
        $blocks = $this->blocksInRegion($region);
        $html = '';

        foreach ($blocks as $block) {
            $html .= $block->render($user);
        }

        return $html;
    }
}
