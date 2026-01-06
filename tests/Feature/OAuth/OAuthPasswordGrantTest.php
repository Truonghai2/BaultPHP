<?php

namespace Tests\Feature\OAuth;

use Core\Support\Facades\Hash;
use Modules\User\Infrastructure\Models\OAuth\Client;
use Modules\User\Infrastructure\Models\User;
use Tests\TestCase;

class OAuthPasswordGrantTest extends TestCase
{
    protected Client $client;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Create a password grant client
        $this->client = Client::create([
            'id' => 'password-client-id',
            'name' => 'Password Grant Client',
            'secret' => password_hash('client-secret', PASSWORD_BCRYPT),
            'redirect' => 'http://localhost',
            'personal_access_client' => false,
            'password_client' => true,
            'revoked' => false,
        ]);
    }

    public function test_can_obtain_token_with_valid_credentials(): void
    {
        $response = $this->post('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => 'password-client-id',
            'client_secret' => 'client-secret',
            'username' => 'test@example.com',
            'password' => 'password123',
            'scope' => '',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
            'refresh_token',
        ]);
    }

    public function test_invalid_credentials_return_error(): void
    {
        $response = $this->post('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => 'password-client-id',
            'client_secret' => 'client-secret',
            'username' => 'test@example.com',
            'password' => 'wrong-password',
            'scope' => '',
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'invalid_grant',
        ]);
    }

    public function test_invalid_client_secret_returns_error(): void
    {
        $response = $this->post('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => 'password-client-id',
            'client_secret' => 'wrong-secret',
            'username' => 'test@example.com',
            'password' => 'password123',
            'scope' => '',
        ]);

        $response->assertStatus(401);
    }

    public function test_can_refresh_token(): void
    {
        // First, obtain a token
        $tokenResponse = $this->post('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => 'password-client-id',
            'client_secret' => 'client-secret',
            'username' => 'test@example.com',
            'password' => 'password123',
        ]);

        $refreshToken = $tokenResponse->json('refresh_token');

        // Now use the refresh token
        $response = $this->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => 'password-client-id',
            'client_secret' => 'client-secret',
            'refresh_token' => $refreshToken,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
            'refresh_token',
        ]);

        // The new refresh token should be different (rotation)
        $this->assertNotEquals($refreshToken, $response->json('refresh_token'));
    }

    protected function tearDown(): void
    {
        $this->client->delete();
        $this->user->delete();
        
        parent::tearDown();
    }
}

