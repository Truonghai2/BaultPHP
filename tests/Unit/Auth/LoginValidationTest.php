<?php

namespace Tests\Unit\Auth;

use Tests\Traits\RefreshDatabase;
use Tests\TestCases;

class LoginValidationTest extends TestCases
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // Phải gọi setUp của TestCase để khởi tạo $this->app
        parent::setUp();

        // Thiết lập session driver là 'database' để có thể kiểm tra
        $this->app->make('config')->set('session.driver', 'database');
    }

    public function test_login_request_with_invalid_email_redirects_back_with_errors_and_session_is_saved_to_db(): void
    {
        $invalidData = ['email' => 'not-an-email', 'password' => 'password'];
        $referer = 'http://localhost/login';

        // Sử dụng phương thức `call` từ `Tests\TestCase` để gửi request
        // với header 'Referer'
        $response = $this->call('POST', '/login', $invalidData, [], [], ['HTTP_REFERER' => $referer]);

        // Kiểm tra response là một redirect về trang login
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals($referer, $response->getHeaderLine('Location'));

        $setCookieHeader = $response->getHeaderLine('Set-Cookie');
        $this->assertNotEmpty($setCookieHeader, 'Set-Cookie header should not be empty.');

        preg_match('/bault_session=([^;]+)/', $setCookieHeader, $matches);
        $sessionId = $matches[1] ?? null;
        $this->assertNotNull($sessionId, 'Session ID not found in Set-Cookie header.');

        // Truy cập trực tiếp vào database để kiểm tra session đã được lưu
        $sessionRecord = $this->app->make(\Core\ORM\Connection::class)->connection()->query("SELECT * FROM sessions WHERE id = '{$sessionId}'")->fetch();

        $this->assertNotNull($sessionRecord, "Session with ID '{$sessionId}' was not found in the database.");

        // Giải mã payload và kiểm tra lỗi
        $payload = unserialize(base64_decode($sessionRecord['payload']));
        $this->assertArrayHasKey('errors', $payload['_flash']['new']);
        $this->assertArrayHasKey('email', $payload['_flash']['new']['errors']);
        $this->assertContains('Địa chỉ email không hợp lệ.', $payload['_flash']['new']['errors']['email']);
    }
}