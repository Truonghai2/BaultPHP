<?php

declare(strict_types=1);

namespace Core\Realtime;

use Core\Support\Facades\Log;

/**
 * WebRTC Manager
 *
 * Manages WebRTC signaling and P2P communication setup.
 * Handles signaling server functionality for WebRTC connections.
 *
 * Features:
 * - Signaling server
 * - ICE candidate exchange
 * - SDP offer/answer handling
 * - P2P connection management
 *
 * Use Cases:
 * - Video/audio streaming
 * - Screen sharing
 * - Real-time collaboration
 */
class WebRTCManager
{
    protected array $sessions = [];
    protected array $offers = [];
    protected array $answers = [];
    protected array $iceCandidates = [];

    public function __construct(
        protected array $config = [],
    ) {
    }

    /**
     * Create a WebRTC session
     *
     * @param string $sessionId Session identifier
     * @param array $options Session options
     * @return array Session info
     */
    public function createSession(string $sessionId, array $options = []): array
    {
        $session = [
            'id' => $sessionId,
            'created_at' => time(),
            'options' => $options,
            'peers' => [],
            'status' => 'pending',
        ];

        $this->sessions[$sessionId] = $session;

        Log::info("WebRTC session created", [
            'session_id' => $sessionId,
        ]);

        return $session;
    }

    /**
     * Handle SDP offer
     *
     * @param string $sessionId Session ID
     * @param string $peerId Peer ID
     * @param array $offer SDP offer
     */
    public function handleOffer(string $sessionId, string $peerId, array $offer): void
    {
        if (!isset($this->sessions[$sessionId])) {
            throw new \InvalidArgumentException("Session not found: {$sessionId}");
        }

        $this->offers[$sessionId][$peerId] = $offer;

        // Add peer to session
        $this->sessions[$sessionId]['peers'][$peerId] = [
            'id' => $peerId,
            'status' => 'offering',
            'joined_at' => time(),
        ];

        Log::info("WebRTC offer received", [
            'session_id' => $sessionId,
            'peer_id' => $peerId,
        ]);
    }

    /**
     * Handle SDP answer
     *
     * @param string $sessionId Session ID
     * @param string $peerId Peer ID
     * @param array $answer SDP answer
     */
    public function handleAnswer(string $sessionId, string $peerId, array $answer): void
    {
        if (!isset($this->sessions[$sessionId])) {
            throw new \InvalidArgumentException("Session not found: {$sessionId}");
        }

        $this->answers[$sessionId][$peerId] = $answer;

        // Update peer status
        if (isset($this->sessions[$sessionId]['peers'][$peerId])) {
            $this->sessions[$sessionId]['peers'][$peerId]['status'] = 'answered';
        }

        Log::info("WebRTC answer received", [
            'session_id' => $sessionId,
            'peer_id' => $peerId,
        ]);
    }

    /**
     * Handle ICE candidate
     *
     * @param string $sessionId Session ID
     * @param string $peerId Peer ID
     * @param array $candidate ICE candidate
     */
    public function handleIceCandidate(string $sessionId, string $peerId, array $candidate): void
    {
        if (!isset($this->sessions[$sessionId])) {
            throw new \InvalidArgumentException("Session not found: {$sessionId}");
        }

        if (!isset($this->iceCandidates[$sessionId])) {
            $this->iceCandidates[$sessionId] = [];
        }

        if (!isset($this->iceCandidates[$sessionId][$peerId])) {
            $this->iceCandidates[$sessionId][$peerId] = [];
        }

        $this->iceCandidates[$sessionId][$peerId][] = $candidate;

        Log::debug("WebRTC ICE candidate received", [
            'session_id' => $sessionId,
            'peer_id' => $peerId,
        ]);
    }

    /**
     * Get offer for peer
     *
     * @param string $sessionId Session ID
     * @param string $peerId Peer ID to get offer from
     * @return array|null SDP offer
     */
    public function getOffer(string $sessionId, string $peerId): ?array
    {
        return $this->offers[$sessionId][$peerId] ?? null;
    }

    /**
     * Get answer for peer
     *
     * @param string $sessionId Session ID
     * @param string $peerId Peer ID to get answer from
     * @return array|null SDP answer
     */
    public function getAnswer(string $sessionId, string $peerId): ?array
    {
        return $this->answers[$sessionId][$peerId] ?? null;
    }

    /**
     * Get ICE candidates for peer
     *
     * @param string $sessionId Session ID
     * @param string $peerId Peer ID
     * @return array ICE candidates
     */
    public function getIceCandidates(string $sessionId, string $peerId): array
    {
        return $this->iceCandidates[$sessionId][$peerId] ?? [];
    }

    /**
     * Join session as peer
     *
     * @param string $sessionId Session ID
     * @param string $peerId Peer ID
     * @return array Session info
     */
    public function joinSession(string $sessionId, string $peerId): array
    {
        if (!isset($this->sessions[$sessionId])) {
            throw new \InvalidArgumentException("Session not found: {$sessionId}");
        }

        $this->sessions[$sessionId]['peers'][$peerId] = [
            'id' => $peerId,
            'status' => 'joined',
            'joined_at' => time(),
        ];

        Log::info("Peer joined WebRTC session", [
            'session_id' => $sessionId,
            'peer_id' => $peerId,
        ]);

        return $this->sessions[$sessionId];
    }

    /**
     * Leave session
     *
     * @param string $sessionId Session ID
     * @param string $peerId Peer ID
     */
    public function leaveSession(string $sessionId, string $peerId): void
    {
        if (isset($this->sessions[$sessionId]['peers'][$peerId])) {
            unset($this->sessions[$sessionId]['peers'][$peerId]);
        }

        // Cleanup offers/answers/candidates
        unset($this->offers[$sessionId][$peerId] ?? null);
        unset($this->answers[$sessionId][$peerId] ?? null);
        unset($this->iceCandidates[$sessionId][$peerId] ?? null);

        Log::info("Peer left WebRTC session", [
            'session_id' => $sessionId,
            'peer_id' => $peerId,
        ]);
    }

    /**
     * Get session info
     *
     * @param string $sessionId Session ID
     * @return array|null Session info
     */
    public function getSession(string $sessionId): ?array
    {
        return $this->sessions[$sessionId] ?? null;
    }

    /**
     * List all sessions
     *
     * @return array All sessions
     */
    public function listSessions(): array
    {
        return array_map(function ($session) {
            return [
                'id' => $session['id'],
                'peers_count' => count($session['peers']),
                'status' => $session['status'],
                'created_at' => $session['created_at'],
            ];
        }, $this->sessions);
    }

    /**
     * Cleanup expired sessions
     *
     * @param int $maxAge Maximum age in seconds
     */
    public function cleanupExpiredSessions(int $maxAge = 3600): void
    {
        $now = time();
        $expired = [];

        foreach ($this->sessions as $sessionId => $session) {
            if (($now - $session['created_at']) > $maxAge) {
                $expired[] = $sessionId;
            }
        }

        foreach ($expired as $sessionId) {
            unset($this->sessions[$sessionId]);
            unset($this->offers[$sessionId]);
            unset($this->answers[$sessionId]);
            unset($this->iceCandidates[$sessionId]);
        }

        if (!empty($expired)) {
            Log::info("Cleaned up expired WebRTC sessions", [
                'count' => count($expired),
            ]);
        }
    }

    /**
     * Get statistics
     */
    public function getStats(): array
    {
        $totalPeers = 0;
        foreach ($this->sessions as $session) {
            $totalPeers += count($session['peers'] ?? []);
        }

        return [
            'total_sessions' => count($this->sessions),
            'total_peers' => $totalPeers,
            'sessions' => $this->listSessions(),
        ];
    }
}
