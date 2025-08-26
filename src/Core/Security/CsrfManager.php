<?php

namespace Core\Security;

use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Lớp Core CSRF Manager.
 *
 * Lớp này đóng gói (wraps) implementation của Symfony CSRF, cung cấp một API
 * nhất quán cho toàn bộ ứng dụng. Việc này giúp giảm sự phụ thuộc trực tiếp
 * vào thư viện bên ngoài và dễ dàng thay thế trong tương lai.
 */
class CsrfManager
{
    public function __construct(protected CsrfTokenManagerInterface $symfonyManager)
    {
    }

    /**
     * Lấy giá trị của một CSRF token.
     */
    public function getTokenValue(string $tokenId = '_token'): string
    {
        return $this->symfonyManager->getToken($tokenId)->getValue();
    }

    /**
     * Kiểm tra xem một token có hợp lệ không.
     */
    public function isTokenValid(string $tokenId, ?string $tokenValue): bool
    {
        $token = new CsrfToken($tokenId, $tokenValue ?? '');
        return $this->symfonyManager->isTokenValid($token);
    }
}
