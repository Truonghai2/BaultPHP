<?php

namespace Modules\Cms\Infrastructure\Models;

use Core\ORM\Model;
use Core\ORM\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $page_id
 * @property string $component_class
 * @property array $data
 * @property int $order
 * @property mixed $content
 */
class PageBlock extends Model
{
    protected static string $table = 'page_blocks';

    protected array $fillable = ['page_id', 'component_class', 'data', 'order'];

    protected $casts = [
        'data' => 'array',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function getContentAttribute(): mixed
    {
        return $this->data['content'] ?? null;
    }

}
