<?php

namespace Tests\Feature\OAuth;

use Core\Support\Facades\Hash;
use Modules\User\Infrastructure\Models\OAuth\AccessToken;
use Modules\User\Infrastructure\Models\OAuth\Client;
use Modules\User\Infrastructure\Models\OAuth\RefreshToken;
use Modules\User\Infrastructure\Models\User;
use Tests\TestCase;

class OAuthTokenRevocationTest extends TestCase
{
    protected Client $client;
    protected User $user;
    protected string $accessToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->client = Client::create([
            'id' => 'test-client',
            'name' => 'Test Client',
            'secret' => password_hash('secret', PASSWORD_BCRYPT),
            'redirect' => 'http://localhost',
            'password_client' => true,
            'revoked' => false,
        ]);

        // Obtain an access token
        $response = $this->post('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => 'test-client',
            'client_secret' => 'secret',
            'username' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->accessToken = $response->json('access_token');
    }

    public function test_can_revoke_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
        ])->post('/oauth/revoke');

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Tokens revoked successfully.',
        ]);
    }

    public function test_revoked_token_cannot_be_used(): void
    {
        // Revoke the token
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
        ])->post('/oauth/revoke');

        // Try to use the revoked token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
        ])->get('/api/user');

        $response->assertStatus(401);
    }

    public function test_refresh_tokens_are_revoked_when_access_token_is_revoked(): void
    {
        // Get the access token ID
        $tokenParts = explode('.', $this->accessToken);
        // This is simplified - in real test you'd decode the JWT to get the token ID

        // Revoke the token
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
        ])->post('/oauth/revoke');

        // Check that associated refresh tokens are also revoked
        $revokedRefreshTokens = RefreshToken::where('revoked', true)->count();
        $this->assertGreaterThan(0, $revokedRefreshTokens);
    }

    protected function tearDown(): void
    {
        AccessToken::where('user_id', $this->user->id)->delete();
        RefreshToken::all()->each->delete();
        $this->client->delete();
        $this->user->delete();
        
        parent::tearDown();
    }
}
