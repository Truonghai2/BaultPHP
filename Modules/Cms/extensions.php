<?php

declare(strict_types=1);

use Core\Extension\CoreExtensionPoints as EP;

/**
 * Extension Point registrations for the CMS module.
 */
return [

    // ─── Advertise available block types to other modules ────────────────────
    //
    // Returns a map of  alias => [label, icon]  that appears in the
    // admin block picker.  Third-party modules can extend this list by
    // registering their own EP::BLOCK_TYPES handler.
    EP::BLOCK_TYPES => [Modules\Cms\Extensions\CmsBlockTypeProvider::class, 'types'],

    // ─── Add CMS-related items to the admin sidebar ───────────────────────────
    EP::NAVIGATION_ADMIN => [Modules\Cms\Extensions\CmsAdminNavProvider::class, 'items'],

];
