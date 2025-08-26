<?php

namespace Modules\User\Http\Controllers\Auth;

use Core\Contracts\View\View;
use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Modules\User\Application\Commands\LoginUserCommand;
use Modules\User\Application\Handlers\LoginUserHandler;
use Modules\User\Application\Handlers\LogoutUserHandler;
use Modules\User\Http\Requests\LoginRequest;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

#[Route(prefix: '/auth', name: 'auth.', group: 'web')]
class LoginController extends Controller
{
    #[Route(method: 'GET', uri: '/login', name: 'login.view')]
    public function view(): View
    {
        return view('user::auth.login');
    }

    /**
     * Handle a login request to the application.
     */
    #[Route(
        method: 'POST',
        uri: '/login',
        name: 'login',
        middleware: ['throttle:5,1'],
    )]
    public function login(LoginRequest $request, LoginUserHandler $handler, SessionInterface $session): ResponseInterface
    {
        $data = $request->validated();
        $remember = !empty($request->getParsedBody()['remember'] ?? false);

        $command = new LoginUserCommand($data['email'], $data['password'], $remember);

        $user = $handler->handle($command);

        if ($user) {
            return redirect()->intended(route('home'));
        }

        $input = $request->getParsedBody();
        unset($input['password']);

        return redirect()->back()
            ->withErrors(['email' => __('Invalid credentials.')])
            ->withInput($input);
    }

    /**
     * Log the user out of the application.
     */
    #[Route(method: 'POST', uri: '/logout', name: 'logout')]
    public function logout(LogoutUserHandler $handler): ResponseInterface
    {
        $handler->handle();

        return redirect('/');
    }
}
