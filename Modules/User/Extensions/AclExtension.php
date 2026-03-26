<?php

declare(strict_types=1);

namespace Modules\User\Extensions;

/**
 * Extension Point handler that augments the core ACL permission check.
 *
 * The FILTER receives the primary decision ($allowed bool) plus a context
 * array and can return a modified decision or leave it unchanged.
 *
 * Context keys:
 *   user_id    (int)    – subject user
 *   permission (string) – permission string being checked
 *   subject    (mixed)  – optional subject entity
 */
final class AclExtension
{
    /**
     * @param bool  $allowed  Decision from the primary ACL system.
     * @param array $context  ['user_id', 'permission', 'subject']
     * @return bool           Possibly overridden decision.
     */
    public function augment(bool $allowed, array $context): bool
    {
        // Example: super-admins bypass all permission checks.
        // Replace with your own logic (e.g. check a "super_admin" flag in the DB).
        try {
            $user = auth()->user();
            if ($user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                return true;
            }
        } catch (\Throwable) {
            // Auth not available
        }

        return $allowed;
    }
}
