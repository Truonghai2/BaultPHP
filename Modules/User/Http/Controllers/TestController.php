<?php

namespace Modules\User\Http\Controllers;

use Core\Contracts\Session\SessionInterface;
use Core\Http\Controller;
use Core\ORM\Connection;
use Core\Routing\Attributes\Route;
use Psr\Http\Message\ResponseInterface;

/**
 * Controller này dùng để kiểm tra chức năng của session.
 */
#[Route(group: 'web')] // Đảm bảo route này sử dụng middleware group 'web'
class TestController extends Controller
{
    /**
     * Route này sẽ tạo hoặc cập nhật session và lưu vào CSDL.
     * Truy cập: /test-session
     */
    #[Route(method: 'GET', uri: '/test-session', name: 'test.session')]
    public function testSession(SessionInterface $session): ResponseInterface
    {
        $counter = $session->get('test_counter', 0);
        $counter++;

        $session->set('test_counter', $counter);

        $sessionId = $session->getId();
        $body = '<h1>Kiểm tra Session</h1>';
        $body .= '<p>Session ID hiện tại: <code>' . htmlspecialchars($sessionId) . '</code></p>';
        $body .= "<p>Giá trị 'test_counter' trong session: <strong>" . htmlspecialchars((string)$counter) . '</strong></p>';
        $body .= '<p>Mỗi lần bạn tải lại trang, counter sẽ tăng lên.</p>';
        $body .= '<p>Bây giờ, hãy kiểm tra bảng <code>sessions</code> trong cơ sở dữ liệu của bạn. Bạn sẽ thấy một bản ghi có ID tương ứng.</p>';

        return response($body);
    }

    /**
     * Route này sẽ cố tình gây ra lỗi CSDL để kiểm tra logging.
     * Truy cập: /test-db-error
     */
    #[Route(method: 'GET', uri: '/test-db-error', name: 'test.db_error')]
    public function testDatabaseError(Connection $db): ResponseInterface
    {
        try {
            $db->query('SELECT * FROM this_table_does_not_exist');
        } finally {
            return response('Đã cố gắng gây ra lỗi CSDL. Vui lòng kiểm tra file log của bạn.', 500);
        }
    }
}
