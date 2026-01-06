<?php

namespace Modules\Cms\Infrastructure\Models;

use Core\ORM\Model;
use Core\ORM\Relations\BelongsTo;
use Core\Audit\Traits\Auditable;
use Modules\User\Infrastructure\Models\User;

/**
 * Block Instance Model
 * 
 * Đại diện cho một instance cụ thể của block được đặt trên trang
 * 
 * @property int $id
 * @property int $block_type_id
 * @property int $region_id
 * @property string $context_type
 * @property int|null $context_id
 * @property string|null $title
 * @property array|null $config
 * @property string|null $content
 * @property int $weight
 * @property bool $visible
 * @property string $visibility_mode
 * @property array|null $visibility_rules
 * @property array|null $allowed_roles
 * @property array|null $denied_roles
 * @property int|null $created_by
 * @property string|null $last_modified_at
 * @property-read BlockType $blockType
 * @property-read BlockRegion $region
 * @property-read User|null $creator
 */
class BlockInstance extends Model
{
    use Auditable;
    
    protected static string $table = 'block_instances';

    protected array $fillable = [
        'block_type_id',
        'region_id',
        'context_type',
        'context_id',
        'title',
        'config',
        'content',
        'weight',
        'visible',
        'visibility_mode',
        'visibility_rules',
        'allowed_roles',
        'denied_roles',
        'created_by',
        'last_modified_at',
    ];

    protected $casts = [
        'config' => 'array',
        'visibility_rules' => 'array',
        'allowed_roles' => 'array',
        'denied_roles' => 'array',
        'weight' => 'integer',
        'visible' => 'boolean',
        'last_modified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Block type relationship
     */
    public function blockType(): BelongsTo
    {
        return $this->belongsTo(BlockType::class);
    }

    /**
     * Region relationship
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(BlockRegion::class);
    }

    /**
     * Creator relationship
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get context entity (polymorphic)
     */
    public function context()
    {
        if ($this->context_type === 'page') {
            return Page::find($this->context_id);
        }
        
        if ($this->context_type === 'user') {
            return User::find($this->context_id);
        }

        return null;
    }

    /**
     * Instantiate block object
     */
    public function instantiate(): mixed
    {
        return $this->blockType->instantiate();
    }

    /**
     * Render block content
     */
    public function render(?User $user = null): string
    {
        // Check visibility
        if (!$this->isVisibleTo($user)) {
            return '';
        }

        $block = $this->instantiate();
        
        if (method_exists($block, 'render')) {
            return $block->render($this);
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

        // Check role-based permissions
        if ($user) {
            $userRoles = $user->getRoles();

            // Check denied roles first
            if ($this->denied_roles && count(array_intersect($userRoles, $this->denied_roles)) > 0) {
                return false;
            }

            // Check allowed roles
            if ($this->allowed_roles && count(array_intersect($userRoles, $this->allowed_roles)) === 0) {
                return false;
            }
        } else {
            // Guest user - check if 'guest' role is allowed
            if ($this->allowed_roles && !in_array('guest', $this->allowed_roles)) {
                return false;
            }
        }

        // Check visibility rules
        if ($this->visibility_mode === 'conditional' && $this->visibility_rules) {
            return $this->evaluateVisibilityRules($user);
        }

        return true;
    }

    /**
     * Evaluate visibility rules
     */
    protected function evaluateVisibilityRules(?User $user): bool
    {
        // TODO: Implement complex visibility rules
        // Ví dụ: chỉ hiện trên một số pages, chỉ hiện vào giờ nhất định...
        return true;
    }

    /**
     * Move block up (decrease weight)
     */
    public function moveUp(): void
    {
        if ($this->weight > 0) {
            $this->weight--;
            $this->save();
        }
    }

    /**
     * Move block down (increase weight)
     */
    public function moveDown(): void
    {
        $this->weight++;
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
     * Scope: Visible blocks only
     */
    public function scopeVisible($query)
    {
        return $query->where('visible', true);
    }

    /**
     * Scope: By context
     */
    public function scopeForContext($query, string $contextType, ?int $contextId = null)
    {
        $query->where('context_type', $contextType);
        
        if ($contextId !== null) {
            $query->where('context_id', $contextId);
        }

        return $query;
    }

    /**
     * Scope: By region
     */
    public function scopeInRegion($query, $regionNameOrId)
    {
        if (is_numeric($regionNameOrId)) {
            return $query->where('region_id', $regionNameOrId);
        }

        return $query->join('block_regions', 'block_instances.region_id', '=', 'block_regions.id')
            ->where('block_regions.name', $regionNameOrId)
            ->select('block_instances.*');
    }

    /**
     * Scope: Ordered by weight
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('weight', 'asc');
    }
}

