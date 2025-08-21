<?php

namespace Modules\User\Http\Controllers\Auth;

use Core\Auth\AuthManager;
use Core\Contracts\View\View;
use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Core\Support\Facades\Log;
use Modules\User\Application\Commands\LoginUserCommand;
use Modules\User\Application\Handlers\LoginUserHandler;
use Modules\User\Http\Requests\LoginRequest;
use Psr\Http\Message\ResponseInterface;

#[Route(prefix: '/auth', name: 'auth.')]
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
    #[Route(method: 'POST', uri: '/login', name: 'login')]
    public function login(LoginRequest $request, LoginUserHandler $handler): ResponseInterface
    {
        // By type-hinting LoginRequest, validation has already passed.
        $validatedData = $request->validated();
        $parsedBody = $request->getParsedBody();

        $remember = !empty($parsedBody['remember']);
        $oauthRedirectUrl = $parsedBody['redirect'] ?? null;

        Log::info('Login attempt', [
            'email' => $validatedData['email'],
            'remember' => $remember,
            'redirect' => $oauthRedirectUrl,
        ]);

        $command = new LoginUserCommand($validatedData['email'], $validatedData['password'], $remember);

        if ($handler->handle($command)) {
            return redirect()->intended($oauthRedirectUrl ?? route('home'));
        }

        Log::warning('Login failed', ['email' => $validatedData['email']]);

        return redirect()->back()
            ->withErrors(['email' => 'Thông tin đăng nhập không chính xác.'])
            ->withInput($request->getParsedBody());
    }

    /**
     * Log the user out of the application.
     */
    #[Route(method: 'POST', uri: '/logout', name: 'logout')]
    public function logout(AuthManager $auth): ResponseInterface
    {
        // All logout logic (clearing user, invalidating session) is now encapsulated in the guard.
        $auth->guard('web')->logout();

        return redirect('/');
    }
}
