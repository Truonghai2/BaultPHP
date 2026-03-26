<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\ResponseFactory;
use Core\Realtime\WebRTCManager;
use Core\Routing\Attributes\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * WebRTC Controller
 *
 * Handles WebRTC signaling for P2P communication.
 */
class WebRTCController
{
    public function __construct(
        private readonly WebRTCManager $webrtcManager,
        private readonly ResponseFactory $responseFactory,
    ) {
    }

    /**
     * Create WebRTC session
     */
    #[Route('/webrtc/sessions', method: 'POST')]
    public function createSession(ServerRequestInterface $request): ResponseInterface
    {
        $body = json_decode((string) $request->getBody(), true);
        
        $sessionId = $body['session_id'] ?? bin2hex(random_bytes(16));
        $options = $body['options'] ?? [];

        $session = $this->webrtcManager->createSession($sessionId, $options);

        return $this->responseFactory->json($session);
    }

    /**
     * Join session
     */
    #[Route('/webrtc/sessions/{sessionId}/join', method: 'POST')]
    public function joinSession(ServerRequestInterface $request, string $sessionId): ResponseInterface
    {
        $body = json_decode((string) $request->getBody(), true);
        $peerId = $body['peer_id'] ?? bin2hex(random_bytes(8));

        $session = $this->webrtcManager->joinSession($sessionId, $peerId);

        return $this->responseFactory->json([
            'session' => $session,
            'peer_id' => $peerId,
        ]);
    }

    /**
     * Handle SDP offer
     */
    #[Route('/webrtc/sessions/{sessionId}/offer', method: 'POST')]
    public function handleOffer(ServerRequestInterface $request, string $sessionId): ResponseInterface
    {
        $body = json_decode((string) $request->getBody(), true);
        
        $peerId = $body['peer_id'] ?? null;
        $offer = $body['offer'] ?? null;

        if (!$peerId || !$offer) {
            return $this->responseFactory->json([
                'error' => 'peer_id and offer are required',
            ], 400);
        }

        $this->webrtcManager->handleOffer($sessionId, $peerId, $offer);

        return $this->responseFactory->json([
            'status' => 'offer_received',
        ]);
    }

    /**
     * Handle SDP answer
     */
    #[Route('/webrtc/sessions/{sessionId}/answer', method: 'POST')]
    public function handleAnswer(ServerRequestInterface $request, string $sessionId): ResponseInterface
    {
        $body = json_decode((string) $request->getBody(), true);
        
        $peerId = $body['peer_id'] ?? null;
        $answer = $body['answer'] ?? null;

        if (!$peerId || !$answer) {
            return $this->responseFactory->json([
                'error' => 'peer_id and answer are required',
            ], 400);
        }

        $this->webrtcManager->handleAnswer($sessionId, $peerId, $answer);

        return $this->responseFactory->json([
            'status' => 'answer_received',
        ]);
    }

    /**
     * Handle ICE candidate
     */
    #[Route('/webrtc/sessions/{sessionId}/ice', method: 'POST')]
    public function handleIceCandidate(ServerRequestInterface $request, string $sessionId): ResponseInterface
    {
        $body = json_decode((string) $request->getBody(), true);
        
        $peerId = $body['peer_id'] ?? null;
        $candidate = $body['candidate'] ?? null;

        if (!$peerId || !$candidate) {
            return $this->responseFactory->json([
                'error' => 'peer_id and candidate are required',
            ], 400);
        }

        $this->webrtcManager->handleIceCandidate($sessionId, $peerId, $candidate);

        return $this->responseFactory->json([
            'status' => 'candidate_received',
        ]);
    }

    /**
     * Get offer for peer
     */
    #[Route('/webrtc/sessions/{sessionId}/offer/{peerId}', method: 'GET')]
    public function getOffer(string $sessionId, string $peerId): ResponseInterface
    {
        $offer = $this->webrtcManager->getOffer($sessionId, $peerId);

        if (!$offer) {
            return $this->responseFactory->json([
                'error' => 'Offer not found',
            ], 404);
        }

        return $this->responseFactory->json($offer);
    }

    /**
     * Get answer for peer
     */
    #[Route('/webrtc/sessions/{sessionId}/answer/{peerId}', method: 'GET')]
    public function getAnswer(string $sessionId, string $peerId): ResponseInterface
    {
        $answer = $this->webrtcManager->getAnswer($sessionId, $peerId);

        if (!$answer) {
            return $this->responseFactory->json([
                'error' => 'Answer not found',
            ], 404);
        }

        return $this->responseFactory->json($answer);
    }

    /**
     * Get ICE candidates for peer
     */
    #[Route('/webrtc/sessions/{sessionId}/ice/{peerId}', method: 'GET')]
    public function getIceCandidates(string $sessionId, string $peerId): ResponseInterface
    {
        $candidates = $this->webrtcManager->getIceCandidates($sessionId, $peerId);

        return $this->responseFactory->json([
            'candidates' => $candidates,
        ]);
    }

    /**
     * Get session info
     */
    #[Route('/webrtc/sessions/{sessionId}', method: 'GET')]
    public function getSession(string $sessionId): ResponseInterface
    {
        $session = $this->webrtcManager->getSession($sessionId);

        if (!$session) {
            return $this->responseFactory->json([
                'error' => 'Session not found',
            ], 404);
        }

        return $this->responseFactory->json($session);
    }

    /**
     * Leave session
     */
    #[Route('/webrtc/sessions/{sessionId}/leave', method: 'POST')]
    public function leaveSession(ServerRequestInterface $request, string $sessionId): ResponseInterface
    {
        $body = json_decode((string) $request->getBody(), true);
        $peerId = $body['peer_id'] ?? null;

        if (!$peerId) {
            return $this->responseFactory->json([
                'error' => 'peer_id is required',
            ], 400);
        }

        $this->webrtcManager->leaveSession($sessionId, $peerId);

        return $this->responseFactory->json([
            'status' => 'left',
        ]);
    }

    /**
     * Get statistics
     */
    #[Route('/webrtc/stats', method: 'GET')]
    public function stats(): ResponseInterface
    {
        $stats = $this->webrtcManager->getStats();

        return $this->responseFactory->json($stats);
    }
}
