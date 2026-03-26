<?php

declare(strict_types=1);

namespace Modules\Cms\Extensions;

/**
 * Advertises the CMS module's built-in block types via the BLOCK_TYPES
 * extension point so the admin UI block-picker can list them dynamically.
 */
final class CmsBlockTypeProvider
{
    /**
     * @return array<string, array{label:string, icon?:string}>
     */
    public function types(array $context): array
    {
        return [
            'hero'          => ['label' => 'Hero Banner',      'icon' => 'image'],
            'text'          => ['label' => 'Rich Text',        'icon' => 'align-left'],
            'gallery'       => ['label' => 'Image Gallery',    'icon' => 'grid'],
            'cta'           => ['label' => 'Call to Action',   'icon' => 'zap'],
            'html'          => ['label' => 'Custom HTML',      'icon' => 'code'],
            'media'         => ['label' => 'Media Library',    'icon' => 'film'],
            'header'        => ['label' => 'Site Header',      'icon' => 'layout'],
            'footer'        => ['label' => 'Site Footer',      'icon' => 'layout'],
        ];
    }
}
