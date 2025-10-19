<?php

namespace Modules\User\Http\Controllers\Auth;

use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Modules\User\Application\Commands\LoginUserCommand;
use Modules\User\Application\Handlers\LoginUserHandler;
use Modules\User\Application\Handlers\LogoutUserHandler;
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
    public function login(LoginRequest $request, LoginUserHandler $handler): ResponseInterface
    {
        $data = $request->validated();
        $remember = !empty($request->getParsedBody()['remember'] ?? false);

        $command = new LoginUserCommand($data['email'], $data['password'], $remember);

        $user = $handler->handle($command);

        if ($user) {
            return redirect()->intended(route('home'))
                ->with('success', __('Đăng nhập thành công!'));
        }

        return redirect()->back()
            ->withErrors(['email' => __('Thông tin đăng nhập không chính xác.')])
            ->withInput($request->except('password'));
    }

    /**
     * Log the user out of the application.
     */
    #[Route(method: 'POST', uri: '/logout', name: 'logout')]
    public function logout(LogoutUserHandler $handler)
    {
        $handler->handle();

        return redirect('/')->with('success', __('Đăng xuất thành công!'));
    }
}
