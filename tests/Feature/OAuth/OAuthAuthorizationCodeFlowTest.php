<?php

namespace Tests\Feature\OAuth;

use Modules\User\Infrastructure\Models\OAuth\Client;
use Modules\User\Infrastructure\Models\User;
use Tests\TestCase;

class OAuthAuthorizationCodeFlowTest extends TestCase
{
    protected Client $client;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::factory()->create();

        // Create a test OAuth client
        $this->client = Client::create([
            'id' => 'test-client-id',
            'name' => 'Test Client',
            'secret' => password_hash('test-secret', PASSWORD_BCRYPT),
            'redirect' => 'http://localhost:3000/callback',
            'personal_access_client' => false,
            'password_client' => false,
            'revoked' => false,
        ]);
    }

    public function test_authorization_endpoint_redirects_to_login_when_not_authenticated(): void
    {
        $response = $this->get('/oauth/authorize?client_id=test-client-id&redirect_uri=http://localhost:3000/callback&response_type=code');

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_authorization_endpoint_shows_approval_screen_when_authenticated(): void
    {
        $this->actingAs($this->user);

        $response = $this->get('/oauth/authorize?client_id=test-client-id&redirect_uri=http://localhost:3000/callback&response_type=code');

        $response->assertStatus(200);
        $response->assertSee('Authorize Application');
        $response->assertSee('Test Client');
    }

    public function test_user_can_approve_authorization_request(): void
    {
        $this->actingAs($this->user);

        $response = $this->post('/oauth/authorize', [
            'client_id' => 'test-client-id',
            'redirect_uri' => 'http://localhost:3000/callback',
            'response_type' => 'code',
            'state' => 'random-state',
            'action' => 'approve',
        ]);

        $response->assertStatus(302);
        $this->assertStringContainsString('code=', $response->headers->get('Location'));
    }

    public function test_user_can_deny_authorization_request(): void
    {
        $this->actingAs($this->user);

        $response = $this->post('/oauth/authorize', [
            'client_id' => 'test-client-id',
            'redirect_uri' => 'http://localhost:3000/callback',
            'response_type' => 'code',
            'state' => 'random-state',
            'action' => 'deny',
        ]);

        $response->assertStatus(302);
        $this->assertStringContainsString('error=access_denied', $response->headers->get('Location'));
    }

    public function test_invalid_client_id_returns_error(): void
    {
        $this->actingAs($this->user);

        $response = $this->get('/oauth/authorize?client_id=invalid-client&redirect_uri=http://localhost:3000/callback&response_type=code');

        $response->assertStatus(401);
    }

    protected function tearDown(): void
    {
        $this->client->delete();
        $this->user->delete();
        
        parent::tearDown();
    }
}

