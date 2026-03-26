<?php

declare(strict_types=1);

namespace Modules\User\Extensions;

/**
 * Contributes global view variables originating from the User module.
 *
 * These variables are automatically available in every Blade template
 * without requiring controllers to pass them individually.
 */
final class UserViewDataProvider
{
    /**
     * @return array<string, mixed>
     */
    public function provide(array $context): array
    {
        $user = null;
        $isAuthenticated = false;

        try {
            $auth = auth();
            $isAuthenticated = $auth->check();
            $user = $isAuthenticated ? $auth->user() : null;
        } catch (\Throwable) {
            // Auth not available during CLI / early boot
        }

        return [
            'currentUser'       => $user,
            'isAuthenticated'   => $isAuthenticated,
        ];
    }
}
