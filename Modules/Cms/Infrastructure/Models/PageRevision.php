<?php

declare(strict_types=1);

namespace Modules\Cms\Infrastructure\Models;

use Core\ORM\Model;
use Core\ORM\Relations\BelongsTo;
use Modules\User\Infrastructure\Models\User;
use Core\Audit\Traits\Auditable;

/**
 * Page Revision Model
 * 
 * Stores historical versions of pages for rollback and audit
 * 
 * @property int $id
 * @property int $page_id
 * @property int $user_id
 * @property array $content
 * @property int $revision_number
 * @property string|null $change_summary
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 * @property-read Page $page
 * @property-read User $user
 */
class PageRevision extends Model
{
    use Auditable;
    
    protected static string $table = 'page_revisions';

    protected array $fillable = [
        'page_id',
        'user_id',
        'content',
        'revision_number',
        'change_summary',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'content' => 'array',
        'revision_number' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the page this revision belongs to
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * Get the user who created this revision
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get formatted change summary
     */
    public function getFormattedSummary(): string
    {
        return $this->change_summary ?? 'No summary provided';
    }

    /**
     * Get revision age (human readable)
     */
    public function getAge(): string
    {
        return $this->created_at->diffForHumans();
    }
}

