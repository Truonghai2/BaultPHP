<?php

namespace Modules\Cms\Infrastructure\Models;

use Core\ORM\Model;
use Core\ORM\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int $user_id
 * @property-read \Core\Support\Collection<int, \Modules\Cms\Infrastructure\Models\PageBlock> $blocks
 */
class Page extends Model
{
    protected static string $table = 'pages';

    protected array $fillable = ['name', 'slug', 'user_id'];

    public function blocks(): HasMany
    {
        $relation = $this->hasMany(PageBlock::class);
        $relation->getQuery()->orderBy('order');
        return $relation;
    }
}
