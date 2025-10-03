<?php

namespace Core\Debug;

use Core\Auth\AuthManager;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

/**
 * Class AuthCollector
 *
 * Thu thập thông tin về người dùng đang được xác thực.
 */
class AuthCollector extends DataCollector implements Renderable
{
    protected AuthManager $auth;

    public function __construct(AuthManager $auth)
    {
        $this->auth = $auth;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(): array
    {
        $user = $this->auth->user();
        $guardName = $this->auth->guard();

        if (!$user) {
            return [
                'user_string' => 'Guest',
                'guard' => $guardName,
                'auth' => 'Not Authenticated.',
            ];
        }

        $userData = [];
        if (method_exists($user, 'toArray')) {
            $userData = $user->toArray();
        }

        return [
            'user_string' => sprintf('%s (ID: %s)', get_class($user), $user->getAuthIdentifier()),
            'user_id' => $user->getAuthIdentifier(),
            'guard' => $guardName,
            'auth' => $this->getDataFormatter()->formatVar($userData),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'auth';
    }

    /**
     * {@inheritdoc}
     */
    public function getWidgets(): array
    {
        return [
            'auth' => [
                'icon' => 'user',
                'widget' => 'PhpDebugBar.Widgets.VariableListWidget',
                'map' => 'auth.auth',
                'default' => '{}',
            ],
            'auth:badge' => [
                'map' => 'auth.user_id',
                'default' => 'Guest',
            ],
        ];
    }
}
