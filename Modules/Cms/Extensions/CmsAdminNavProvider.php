<?php

declare(strict_types=1);

namespace Modules\Cms\Extensions;

/**
 * Contributes CMS navigation items to the admin sidebar.
 */
final class CmsAdminNavProvider
{
    /**
     * @return list<array{label:string, url:string, icon?:string, order?:int, children?:array}>
     */
    public function items(array $context): array
    {
        $canManageCms = false;

        try {
            $user = auth()->user();
            $canManageCms = $user && $user->can('manage-cms');
        } catch (\Throwable) {
            // Auth not available
        }

        if (!$canManageCms) {
            return [];
        }

        return [
            [
                'label'    => 'Content',
                'url'      => '/admin/pages',
                'icon'     => 'file-text',
                'order'    => 10,
                'children' => [
                    ['label' => 'Pages',    'url' => '/admin/pages'],
                    ['label' => 'Blocks',   'url' => '/admin/blocks'],
                    ['label' => 'Regions',  'url' => '/admin/regions'],
                    ['label' => 'Media',    'url' => '/admin/media'],
                    ['label' => 'Templates','url' => '/admin/templates'],
                ],
            ],
        ];
    }
}
