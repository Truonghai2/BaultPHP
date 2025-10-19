<?php

namespace Modules\User\Http\Controllers\Auth;

use Core\Auth\AuthManager;
use Core\Contracts\Session\SessionInterface;
use Core\Contracts\View\View;
use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Modules\User\Application\Commands\RegisterUserCommand;
use Modules\User\Application\Handlers\RegisterUserHandler;
use Modules\User\Http\Requests\RegisterRequest;
use Psr\Http\Message\ResponseInterface;

#[Route(prefix: '/auth', name: 'auth.')]
class RegisterController extends Controller
{
    #[Route(method: 'GET', uri: '/register', name: 'register.view')]
    public function view(): View
    {
        return view('user::auth.register');
    }

    /**
     * Handle a registration request for the application.
     */
    #[Route(method: 'POST', uri: '/register', name: 'register')]
    public function register(RegisterRequest $request, RegisterUserHandler $handler, AuthManager $auth, SessionInterface $session): ResponseInterface
    {
        $data = $request->validated();

        $command = new RegisterUserCommand($data['name'], $data['email'], $data['password']);

        $user = $handler->handle($command);

        $auth->guard('web')->login($user);

        $session->regenerate(true);

        return redirect(route('home'));
    }
}
