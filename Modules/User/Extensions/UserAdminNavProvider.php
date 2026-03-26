<?php

declare(strict_types=1);

namespace Modules\User\Extensions;

/**
 * Contributes User-module navigation items to the admin sidebar.
 *
 * Each item follows the contract:
 *   [label, url, icon?, order?, children?[]]
 */
final class UserAdminNavProvider
{
    /**
     * @return list<array{label:string, url:string, icon?:string, order?:int, children?:array}>
     */
    public function items(array $context): array
    {
        $canManageUsers = false;

        try {
            $user = auth()->user();
            $canManageUsers = $user && $user->can('manage-users');
        } catch (\Throwable) {
            // Auth not available
        }

        if (!$canManageUsers) {
            return [];
        }

        return [
            [
                'label'    => 'Users',
                'url'      => '/admin/users',
                'icon'     => 'users',
                'order'    => 20,
                'children' => [
                    ['label' => 'All Users',   'url' => '/admin/users'],
                    ['label' => 'Roles',       'url' => '/admin/roles'],
                    ['label' => 'Permissions', 'url' => '/admin/permissions'],
                ],
            ],
        ];
    }
}
