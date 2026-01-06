<?php

namespace Core\Session;

use Core\Contracts\Session\SessionHandlerInterface;
use Core\Database\Swoole\SwoolePdoPool;
use Core\Support\Facades\Log;
use PDO;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Optimized PDO Session Handler với smart write và improved performance.
 * 
 * Improvements:
 * - Smart write: Chỉ update khi có thay đổi hoặc sau interval
 * - Batch garbage collection
 * - Payload size tracking
 * - Better error handling và logging
 */
class OptimizedSwoolePdoSessionHandler implements SessionHandlerInterface
{
    /**
     * Minimum interval giữa các lần update last_activity (seconds)
     */
    private const MIN_UPDATE_INTERVAL = 60;
    
    /**
     * Batch size cho garbage collection
     */
    private const GC_BATCH_SIZE = 1000;
    
    /**
     * Warning threshold cho payload size (bytes)
     */
    private const PAYLOAD_SIZE_WARNING = 102400; // 100KB
    
    /**
     * Cache của session hiện tại để tránh re-read
     */
    private ?array $currentSession = null;
    
    public function __construct(
        private string $connectionName,
        private string $table,
        private int $lifetime,
    ) {
    }

    /**
     * Helper để execute query với connection pooling
     */
    private function withConnection(callable $callback): mixed
    {
        $pdo = null;
        try {
            $pdo = SwoolePdoPool::get($this->connectionName);
            return $callback($pdo);
        } finally {
            if ($pdo) {
                SwoolePdoPool::put($pdo, $this->connectionName);
            }
        }
    }

    public function open(string $savePath, string $sessionName): bool
    {
        return true;
    }

    public function close(): bool
    {
        // Clear cache
        $this->currentSession = null;
        return true;
    }

    public function read(string $sessionId): string|false
    {
        return $this->withConnection(function (PDO $pdo) use ($sessionId) {
            $sql = "SELECT id, user_id, payload, last_activity, created_at 
                    FROM {$this->table} 
                    WHERE id = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $sessionId, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                // Cache session data để optimize write
                $this->currentSession = $result;
                return $result['payload'] ?? '';
            }
            
            $this->currentSession = null;
            return '';
        });
    }

    public function write(string $sessionId, string $data): bool
    {
        return $this->withConnection(function (PDO $pdo) use ($sessionId, $data) {
            $currentTime = time();
            $payloadSize = strlen($data);
            
            // Extract user_id từ session data
            $userId = $this->extractUserId($data);
            
            // Warn nếu payload quá lớn
            if ($payloadSize > self::PAYLOAD_SIZE_WARNING) {
                Log::warning('Large session payload detected', [
                    'session_id' => substr($sessionId, 0, 8) . '...',
                    'payload_size' => $payloadSize,
                    'user_id' => $userId,
                ]);
            }
            
            // Check nếu cần update
            if ($this->currentSession) {
                $needsUpdate = $this->needsUpdate($data, $currentTime);
                
                if (!$needsUpdate) {
                    // Skip update nếu không cần thiết
                    return true;
                }
                
                // UPDATE existing session
                return $this->updateExistingSession($pdo, $sessionId, $data, $userId, $currentTime, $payloadSize);
            }
            
            // INSERT new session
            return $this->insertNewSession($pdo, $sessionId, $data, $userId, $currentTime, $payloadSize);
        });
    }

    /**
     * Check xem session có cần update không
     */
    private function needsUpdate(string $newData, int $currentTime): bool
    {
        // Always update nếu payload thay đổi
        if ($this->currentSession['payload'] !== $newData) {
            return true;
        }
        
        // Update nếu last_activity đã cũ hơn MIN_UPDATE_INTERVAL
        $timeSinceUpdate = $currentTime - ($this->currentSession['last_activity'] ?? 0);
        
        return $timeSinceUpdate >= self::MIN_UPDATE_INTERVAL;
    }

    /**
     * Update existing session
     */
    private function updateExistingSession(
        PDO $pdo,
        string $sessionId,
        string $data,
        ?int $userId,
        int $currentTime,
        int $payloadSize
    ): bool {
        // Chỉ update các field thay đổi
        $sql = "UPDATE {$this->table} 
                SET payload = :data,
                    last_activity = :time,
                    lifetime = :lifetime,
                    payload_size = :size";
        
        // Chỉ update user_id nếu khác
        if ($userId !== ($this->currentSession['user_id'] ?? null)) {
            $sql .= ", user_id = :user_id";
        }
        
        // Chỉ update metadata thỉnh thoảng (mỗi 5 phút)
        $shouldUpdateMetadata = ($currentTime % 300) < self::MIN_UPDATE_INTERVAL;
        if ($shouldUpdateMetadata) {
            $sql .= ", ip_address = :ip_address, user_agent = :user_agent";
        }
        
        $sql .= " WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $sessionId, PDO::PARAM_STR);
        $stmt->bindParam(':data', $data, PDO::PARAM_LOB);
        $stmt->bindValue(':time', $currentTime, PDO::PARAM_INT);
        $stmt->bindValue(':lifetime', $this->lifetime, PDO::PARAM_INT);
        $stmt->bindValue(':size', $payloadSize, PDO::PARAM_INT);
        
        if ($userId !== ($this->currentSession['user_id'] ?? null)) {
            $stmt->bindValue(':user_id', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        }
        
        if ($shouldUpdateMetadata) {
            $metadata = $this->getRequestMetadata();
            $stmt->bindValue(':ip_address', $metadata['ip'], PDO::PARAM_STR);
            $stmt->bindValue(':user_agent', $metadata['user_agent'], PDO::PARAM_STR);
        }
        
        return $stmt->execute();
    }

    /**
     * Insert new session
     */
    private function insertNewSession(
        PDO $pdo,
        string $sessionId,
        string $data,
        ?int $userId,
        int $currentTime,
        int $payloadSize
    ): bool {
        $metadata = $this->getRequestMetadata();
        
        $sql = "INSERT INTO {$this->table} 
                (id, user_id, ip_address, user_agent, payload, last_activity, lifetime, created_at, payload_size)
                VALUES (:id, :user_id, :ip_address, :user_agent, :data, :time, :lifetime, :created_at, :size)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $sessionId, PDO::PARAM_STR);
        $stmt->bindValue(':user_id', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':ip_address', $metadata['ip'], PDO::PARAM_STR);
        $stmt->bindValue(':user_agent', $metadata['user_agent'], PDO::PARAM_STR);
        $stmt->bindParam(':data', $data, PDO::PARAM_LOB);
        $stmt->bindValue(':time', $currentTime, PDO::PARAM_INT);
        $stmt->bindValue(':lifetime', $this->lifetime, PDO::PARAM_INT);
        $stmt->bindValue(':created_at', $currentTime, PDO::PARAM_INT);
        $stmt->bindValue(':size', $payloadSize, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Extract user ID từ session payload
     */
    private function extractUserId(string $data): ?int
    {
        $attributes = @unserialize($data);
        
        if (!is_array($attributes)) {
            return null;
        }
        
        // Tìm login_web_* key
        foreach ($attributes as $key => $value) {
            if (str_starts_with($key, 'login_web_') && is_int($value)) {
                return $value;
            }
        }
        
        return null;
    }

    /**
     * Get request metadata (IP, User-Agent)
     */
    private function getRequestMetadata(): array
    {
        $ipAddress = null;
        $userAgent = null;
        
        try {
            if (function_exists('app') && app()->has(ServerRequestInterface::class)) {
                $request = app(ServerRequestInterface::class);
                $serverParams = $request->getServerParams();
                
                // Get IP address
                $ipAddress = $serverParams['remote_addr'] 
                    ?? $serverParams['REMOTE_ADDR'] 
                    ?? null;
                
                // Get User-Agent (limit to 512 chars)
                $userAgent = substr($request->getHeaderLine('User-Agent'), 0, 512) ?: null;
            }
        } catch (\Throwable $e) {
            // Silent fail - metadata is not critical
            Log::debug('Failed to get request metadata for session', [
                'error' => $e->getMessage()
            ]);
        }
        
        return [
            'ip' => $ipAddress,
            'user_agent' => $userAgent,
        ];
    }

    public function destroy(string $sessionId): bool
    {
        return $this->withConnection(function (PDO $pdo) use ($sessionId) {
            $sql = "DELETE FROM {$this->table} WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $sessionId, PDO::PARAM_STR);
            
            return $stmt->execute();
        });
    }

    /**
     * Garbage collection với batch processing
     */
    public function gc(int $maxLifetime): int|false
    {
        return $this->withConnection(function (PDO $pdo) use ($maxLifetime) {
            $totalDeleted = 0;
            $cutoffTime = time() - $maxLifetime;
            
            // Delete in batches để avoid long table locks
            do {
                $sql = "DELETE FROM {$this->table} 
                        WHERE last_activity < :time 
                        ORDER BY last_activity 
                        LIMIT :limit";
                
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':time', $cutoffTime, PDO::PARAM_INT);
                $stmt->bindValue(':limit', self::GC_BATCH_SIZE, PDO::PARAM_INT);
                $stmt->execute();
                
                $deleted = $stmt->rowCount();
                $totalDeleted += $deleted;
                
                // Small sleep giữa các batches để không block queries khác
                if ($deleted === self::GC_BATCH_SIZE) {
                    usleep(10000); // 10ms
                }
                
            } while ($deleted === self::GC_BATCH_SIZE);
            
            if ($totalDeleted > 0) {
                Log::info('Session garbage collection completed', [
                    'deleted' => $totalDeleted,
                    'cutoff_time' => date('Y-m-d H:i:s', $cutoffTime),
                ]);
            }
            
            return $totalDeleted;
        });
    }
}

