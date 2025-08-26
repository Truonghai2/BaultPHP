<?php

namespace Modules\User\Providers;

use Core\BaseServiceProvider;
use DateInterval;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Grant\PasswordGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\ResourceServer;
use Modules\User\Infrastructure\Repositories\OAuth\AccessTokenRepository;
use Modules\User\Infrastructure\Repositories\OAuth\AuthCodeRepository;
use Modules\User\Infrastructure\Repositories\OAuth\ClientRepository;
use Modules\User\Infrastructure\Repositories\OAuth\RefreshTokenRepository;
use Modules\User\Infrastructure\Repositories\OAuth\ScopeRepository;
use Modules\User\Infrastructure\Repositories\OAuth\UserRepository;

class OAuth2ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $this->registerAuthorizationServer();
        $this->registerResourceServer();
    }
    protected function registerAuthorizationServer(): void
    {
        $this->app->singleton(AuthorizationServer::class, function ($app) {
            $config = $app->make('config');

            // Khởi tạo server
            $server = new AuthorizationServer(
                $app->make(ClientRepository::class),
                $app->make(AccessTokenRepository::class),
                $app->make(ScopeRepository::class),
                $config->get('oauth2.private_key'),
                $config->get('oauth2.encryption_key'),
            );

            $authCodeGrant = new AuthCodeGrant(
                $app->make(AuthCodeRepository::class),
                $app->make(RefreshTokenRepository::class),
                new DateInterval($config->get('oauth2.auth_code_ttl')),
            );
            $authCodeGrant->enablePkce();

            $authCodeGrant->setRefreshTokenTTL(new DateInterval($config->get('oauth2.refresh_token_ttl')));

            // 2. Refresh Token Grant
            $refreshTokenGrant = new RefreshTokenGrant(
                $app->make(RefreshTokenRepository::class),
            );
            // Bật tính năng Refresh Token Rotation để tăng cường bảo mật.
            // Client sẽ cần lưu lại refresh_token mới sau mỗi lần làm mới.
            $refreshTokenGrant->enableRefreshTokenRotation();
            $refreshTokenGrant->setRefreshTokenTTL(new DateInterval($config->get('oauth2.refresh_token_ttl')));

            // 3. Password Grant (for 1st-party apps, direct login)
            $passwordGrant = new PasswordGrant(
                $app->make(UserRepository::class),
                $app->make(RefreshTokenRepository::class),
            );
            $passwordGrant->setRefreshTokenTTL(new DateInterval($config->get('oauth2.refresh_token_ttl')));

            // 4. Client Credentials Grant (for machine-to-machine)
            $clientCredentialsGrant = new ClientCredentialsGrant();

            // Thêm các grant type vào server
            $accessTokenTTL = new DateInterval($config->get('oauth2.access_token_ttl'));

            $server->enableGrantType(
                $authCodeGrant,
                $accessTokenTTL,
            );

            $server->enableGrantType(
                $refreshTokenGrant,
                $accessTokenTTL,
            );

            $server->enableGrantType(
                $passwordGrant,
                $accessTokenTTL,
            );

            $server->enableGrantType(
                $clientCredentialsGrant,
                $accessTokenTTL,
            );

            return $server;
        });
    }

    protected function registerResourceServer(): void
    {
        $this->app->singleton(ResourceServer::class, function ($app) {
            $config = $app->make('config');
            return new ResourceServer(
                $app->make(AccessTokenRepository::class),
                $config->get('oauth2.public_key'),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
