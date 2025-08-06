<?php

namespace Modules\Cms\Application\Policies;

use Modules\Cms\Infrastructure\Models\Page;
use Modules\Cms\Infrastructure\Models\PageBlock;
use Modules\User\Infrastructure\Models\User;

class PageBlockPolicy
{
    /**
     * Determine if the user can create new page blocks for a given page.
     * A user can create a block if they can update the parent page.
     *
     * @param User $user
     * @param Page $page The parent page where the block will be created.
     * @return bool
     */
    public function create(User $user, Page $page): bool
    {
        return $user->can('update', $page);
    }

    /**
     * Determine if the user can update a given page block.
     * A user can update a block if they can update the parent page.
     *
     * @param User $user
     * @param PageBlock $block
     * @return bool
     */
    public function update(User $user, PageBlock $block): bool
    {
        return $user->can('update', $block->page);
    }

    /**
     * Determine if the user can delete a given page block.
     * A user can delete a block if they can update the parent page.
     *
     * @param User $user
     * @param PageBlock $block
     * @return bool
     */
    public function delete(User $user, PageBlock $block): bool
    {
        return $user->can('update', $block->page);
    }

    /**
     * Determine if the user can duplicate a given page block.
     * A user can duplicate a block if they can update the parent page.
     *
     * @param User $user
     * @param PageBlock $block
     * @return bool
     */
    public function duplicate(User $user, PageBlock $block): bool
    {
        // The logic is the same as updating or deleting: if you can edit the page,
        // you can duplicate blocks on it.
        return $user->can('update', $block->page);
    }
}
