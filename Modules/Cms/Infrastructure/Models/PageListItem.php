<?php

declare(strict_types=1);

namespace Modules\Cms\Infrastructure\Models;

use Core\ORM\Model;
use Core\ORM\SoftDeletes;

/**
 * PageListItem Read Model
 *
 * This is a denormalized read model for fast page list queries, representing
 * the `page_list_items` table. It is updated by the PageListProjection.
 *
 * @property int $id
 * @property string $page_uuid
 * @property string $name
 * @property string $slug
 * @property int|null $author_id
 * @property string $status
 * @property \Carbon\Carbon|null $published_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class PageListItem extends Model
{
    use SoftDeletes;

    protected static string $table = 'page_list_items';

    protected array $fillable = [
        'page_uuid',
        'name',
        'slug',
        'author_id',
        'status',
        'published_at',
    ];
}