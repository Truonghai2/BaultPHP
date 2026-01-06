<?php

namespace Modules\User\Listeners\OAuth;

use Core\Audit\AuditLogger;
use Core\Support\Facades\Log;
use Modules\User\Events\OAuth\TokenIssuedEvent;
use Modules\User\Events\OAuth\TokenRevokedEvent;
use Modules\User\Events\OAuth\TokenValidationFailedEvent;

class LogOAuthEventListener
{
    public function __construct(
        protected AuditLogger $auditLogger
    ) {
    }

    /**
     * Handle token issued event.
     */
    public function handleTokenIssued(TokenIssuedEvent $event): void
    {
        // Log to file
        Log::info('OAuth token issued', [
            'token_id' => $event->tokenId,
            'user_id' => $event->userId,
            'client_id' => $event->clientId,
            'scopes' => $event->scopes,
            'grant_type' => $event->grantType,
            'ip_address' => $event->ipAddress,
        ]);

        // Log to audit database
        $this->auditLogger->oauth(
            eventType: 'oauth.token.issued',
            data: [
                'token_id' => $event->tokenId,
                'user_id' => $event->userId,
                'client_id' => $event->clientId,
                'scopes' => $event->scopes,
                'grant_type' => $event->grantType,
                'ip_address' => $event->ipAddress,
            ],
            severity: 'info'
        );
    }

    /**
     * Handle token revoked event.
     */
    public function handleTokenRevoked(TokenRevokedEvent $event): void
    {
        // Log to file
        Log::warning('OAuth token revoked', [
            'token_id' => $event->tokenId,
            'user_id' => $event->userId,
            'reason' => $event->reason,
            'ip_address' => $event->ipAddress,
        ]);

        // Log to audit database
        $this->auditLogger->oauth(
            eventType: 'oauth.token.revoked',
            data: [
                'token_id' => $event->tokenId,
                'user_id' => $event->userId,
                'reason' => $event->reason,
                'ip_address' => $event->ipAddress,
            ],
            severity: 'warning'
        );
    }

    /**
     * Handle token validation failed event.
     */
    public function handleTokenValidationFailed(TokenValidationFailedEvent $event): void
    {
        // Log to file
        Log::warning('OAuth token validation failed', [
            'reason' => $event->reason,
            'ip_address' => $event->ipAddress,
            'token_identifier' => $event->tokenIdentifier,
        ]);

        // Log to audit database
        $this->auditLogger->security(
            eventType: 'oauth.token.validation_failed',
            description: 'OAuth token validation failed: ' . $event->reason,
            metadata: [
                'reason' => $event->reason,
                'ip_address' => $event->ipAddress,
                'token_identifier' => $event->tokenIdentifier,
            ],
            severity: 'warning'
        );
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(\Psr\EventDispatcher\EventDispatcherInterface $events): array
    {
        return [
            TokenIssuedEvent::class => 'handleTokenIssued',
            TokenRevokedEvent::class => 'handleTokenRevoked',
            TokenValidationFailedEvent::class => 'handleTokenValidationFailed',
        ];
    }
}

