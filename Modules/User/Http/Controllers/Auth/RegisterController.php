<?php

namespace Modules\User\Http\Controllers\Auth;

use Core\Contracts\Session\SessionInterface;
use Core\Contracts\View\View;
use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Modules\User\Application\Commands\RegisterUserCommand;
use Modules\User\Application\Handlers\RegisterUserHandler;
use Modules\User\Http\Requests\RegisterRequest;
use Core\Support\Facades\Auth;
use Psr\Http\Message\ResponseInterface;

#[Route(prefix: '/auth', name: 'auth.', group: 'web')]
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
    public function register(RegisterRequest $request, RegisterUserHandler $handler): ResponseInterface
    {
        $data = $request->validated();

        $command = new RegisterUserCommand($data['name'], $data['email'], $data['password']);

        $user = $handler->handle($command);
        Auth::guard('web')->login($user);

        return redirect()->intended(route('home'))->with('success', 'Đăng ký thành công!');
    }
}
