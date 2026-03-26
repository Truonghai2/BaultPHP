<?php

declare(strict_types=1);

use Core\Extension\CoreExtensionPoints as EP;

/**
 * Extension Point registrations for the User module.
 *
 * Format options
 * ──────────────
 * Single handler (priority = 10):
 *   EP::SOME_POINT => [SomeClass::class, 'method']
 *
 * Multiple handlers with explicit priority:
 *   EP::SOME_POINT => [
 *       ['handler' => [ClassA::class, 'methodA'], 'priority' => 5],
 *       ['handler' => [ClassB::class, 'methodB'], 'priority' => 20],
 *   ]
 */
return [

    // ─── Contribute auth-related variables to every view ─────────────────────
    //
    // Any template can use $currentUser, $isAuthenticated, $userPermissions
    // without the controller having to pass them explicitly.
    EP::VIEW_GLOBAL_DATA => [Modules\User\Extensions\UserViewDataProvider::class, 'provide'],

    // ─── Add permission-based admin navigation items ──────────────────────────
    //
    // Returns an array of nav item definitions; core merges them with other
    // modules' contributions and sorts by 'order'.
    EP::NAVIGATION_ADMIN => [Modules\User\Extensions\UserAdminNavProvider::class, 'items'],

    // ─── Override / augment permission check ─────────────────────────────────
    //
    // Runs after the primary ACL decision; can grant or deny based on
    // temporary overrides, super-admin flags, etc.
    EP::ACL_CHECK => [Modules\User\Extensions\AclExtension::class, 'augment'],

];
