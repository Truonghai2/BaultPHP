<?php

namespace Modules\User\Http\Controllers\Auth;

use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Modules\User\Application\CommandHandlers\Auth\LoginHandler;
use Modules\User\Application\CommandHandlers\Auth\LogoutHandler;
use Modules\User\Application\Commands\Auth\LoginCommand;
use Modules\User\Http\Requests\LoginRequest;
use Psr\Http\Message\ResponseInterface;

;

#[Route(prefix: '/auth', name: 'auth.', group: 'web')]
class LoginController extends Controller
{
    #[Route(method: 'GET', uri: '/login', name: 'login.view')]
    public function view(): ResponseInterface
    {
        return response(view('user::auth.login'));
    }

    /**
     * Handle a login request to the application.
     */
    #[Route(
        method: 'POST',
        uri: '/login',
        name: 'login',
    )]
    public function login(LoginRequest $request, LoginHandler $handler): ResponseInterface
    {
        $totalStart = microtime(true);
        $logger = app(\Psr\Log\LoggerInterface::class);

        $start = microtime(true);
        $data = $request->validated();
        $logger->info('Login: Validation completed', ['duration_ms' => (microtime(true) - $start) * 1000]);

        $remember = !empty($request->getParsedBody()['remember'] ?? false);
        $command = new LoginCommand($data['email'], $data['password'], $remember);

        $start = microtime(true);
        $user = $handler->handle($command);
        $logger->info('Login: Handler completed', ['duration_ms' => (microtime(true) - $start) * 1000]);

        if ($user) {
            $start = microtime(true);

            // Determine redirect destination based on user roles
            $defaultRoute = 'home';

            // If user is admin, redirect to admin dashboard (if exists)
            if ($user->hasRole('admin') || $user->hasRole('super_admin')) {
                // Try admin dashboard route, fallback to admin pages
                if (route_exists('admin.dashboard')) {
                    $defaultRoute = 'admin.dashboard';
                } elseif (route_exists('admin.pages.index')) {
                    $defaultRoute = 'admin.pages.index';
                }
            }

            $response = redirect()->intended(route($defaultRoute))
                ->with('success', __('Đăng nhập thành công!'));
            $logger->info('Login: Redirect prepared', ['duration_ms' => (microtime(true) - $start) * 1000]);
            $logger->info('Login: Total duration', ['duration_ms' => (microtime(true) - $totalStart) * 1000]);
            return $response;
        }

        return redirect()->back()
            ->withErrors(['email' => __('Thông tin đăng nhập không chính xác.')])
            ->withInput($request->except('password'));
    }

    /**
     * Log the user out of the application.
     */
    #[Route(method: 'POST', uri: '/logout', name: 'logout')]
    public function logout(LogoutHandler $handler)
    {
        $handler->handle();

        return redirect('/')->with('success', __('Đăng xuất thành công!'));
    }
}
