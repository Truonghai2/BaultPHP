<?php

namespace Modules\User\Http\Controllers\Auth;

use Core\Auth\AuthManager;
use Core\Contracts\View\View;
use Core\Http\Controller;
use Core\Routing\Attributes\Route;
use Core\Support\MessageBag;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

#[Route(prefix: '/auth', name: 'auth.')]
class LoginController extends Controller
{
    #[Route(method: 'GET', uri: '/login', name: 'login.view')]
    public function view(): View
    {
        return view('user::auth.login');
    }

    /**
     * login
     *
     * @param ServerRequestInterface $request
     * @param AuthManager $auth
     * @param SessionInterface $session
     * @return ResponseInterface
     */
    #[Route(method: 'POST', uri: '/login', name: 'login')]
    public function login(ServerRequestInterface $request, AuthManager $auth, SessionInterface $session): ResponseInterface
    {
        $data = $request->getParsedBody();
        $credentials = [
            'email' => $data['email'] ?? null,
            'password' => $data['password'] ?? null,
        ];
        $remember = isset($data['remember']);

        if ($auth->guard('web')->attempt($credentials, $remember)) {
            $session->regenerate();

            return redirect()->intended(route('home'));
        }

        // Thay vì dùng withErrors() và withInput(), chúng ta flash trực tiếp vào session.
        // View engine sẽ tự động lấy các giá trị này ra.
        $errors = new MessageBag(['email' => 'Thông tin đăng nhập không chính xác.']);
        $session->flash('errors', $errors);
        $session->flash('_old_input', $request->getParsedBody());

        return redirect()->back();
    }

    /**
     * Xử lý yêu cầu đăng xuất.
     */
    #[Route(method: 'POST', uri: '/logout', name: 'logout')]
    public function logout(AuthManager $auth, SessionInterface $session): ResponseInterface
    {
        $auth->guard('web')->logout();

        $session->invalidate();
        $session->regenerateToken(); // Tạo lại CSRF token sau khi đăng xuất

        return redirect('/');
    }
}
