<?php

namespace Modules\User\Tests\Integration;

use Tests\TestCase;
use Tests\Traits\RefreshDatabase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_user_via_api_endpoint()
    {
        // 1. Arrange
        $userData = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123', // Giả sử có validation
        ];

        // 2. Act
        $response = $this->post('/api/users', $userData);

        // 3. Assert
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertJsonStringContainsString('User created successfully', $response->getContent());
        $this->assertJsonStringContainsString('jane@example.com', $response->getContent());
    }
}