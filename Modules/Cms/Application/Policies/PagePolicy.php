<?php

namespace Modules\Cms\Application\Policies;

use Core\Auth\Access\Response;
use Modules\Cms\Infrastructure\Models\Page;
use Modules\User\Infrastructure\Models\User;

class PagePolicy
{
    /**
     * Perform pre-authorization checks.
     * This method is called before any other method in the policy.
     *
     * @param  \Modules\User\Infrastructure\Models\User  $user
     * @param  string  $ability The permission being checked.
     * @return void|bool Returns true to grant all permissions.
     */
    public function before(User $user, string $ability)
    {
        // Super admins can do anything.
        if ($user->can('system.manage-all')) {
            return true;
        }
    }

    /**
     * Determine whether the user can view any pages.
     *
     * @param  \Modules\User\Infrastructure\Models\User  $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->can('pages:view');
    }

    /**
     * Determine whether the user can view the page.
     *
     * @param  \Modules\User\Infrastructure\Models\User  $user
     * @param  \Modules\Cms\Infrastructure\Models\Page  $page
     * @return bool
     */
    public function view(User $user, Page $page): bool
    {
        // For now, any user who can view the list can view a single page.
        return $user->can('pages:view');
    }

    /**
     * Determine whether the user can create pages.
     *
     * @param  \Modules\User\Infrastructure\Models\User  $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->can('pages:create');
    }

    /**
     * Determine whether the user can update the page.
     *
     * @param  \Modules\User\Infrastructure\Models\User  $user
     * @param  \Modules\Cms\Infrastructure\Models\Page  $page
     * @return \Core\Auth\Access\Response|bool
     */
    public function update(User $user, Page $page)
    {
        // Only the author of the page can update it, provided they have the general update permission.
        if ($user->can('pages:update')) {
            return $user->id === $page->user_id
                ? Response::allow()
                : Response::deny('You do not own this page.');
        }
        return false;
    }

    /**
     * Determine whether the user can delete the page.
     */
    public function delete(User $user, Page $page)
    {
        if ($user->can('pages:delete')) {
            return $user->id === $page->user_id
                ? Response::allow()
                : Response::deny('You do not own this page.');
        }
        return false;
    }
}
