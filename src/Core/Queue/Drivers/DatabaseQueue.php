<?php

namespace Core\Queue\Drivers;

use Core\Application;
use Core\Contracts\Queue\Job;
use Core\Contracts\Queue\Queue;
use DateTimeInterface;
use PDO;

class DatabaseQueue implements Queue
{
    protected PDO $pdo;
    protected string $defaultQueue;
    protected string $table;

    public function __construct(protected Application $app, protected array $config)
    {
        $this->pdo = $app->make(PDO::class);
        $this->defaultQueue = $config['queue'] ?? 'default';
        $this->table = $config['table'] ?? 'jobs';
    }

    public function push(Job $job, ?string $queue = null): void
    {
        $this->pushToDatabase($this->createPayload($job), $queue);
    }

    public function later($delay, Job $job, ?string $queue = null): void
    {
        $this->pushToDatabase($this->createPayload($job), $queue, $this->getSeconds($delay));
    }

    public function pop(?string $queue = null): ?Job
    {
        $queueName = $this->getQueueName($queue);

        // Bắt đầu một transaction để đảm bảo tính nguyên tử
        $this->pdo->beginTransaction();

        try {
            // Lấy job tiếp theo và khóa nó lại để các worker khác không thể lấy
            $stmt = $this->pdo->prepare(
                "SELECT * FROM {$this->table}
                 WHERE queue = :queue AND reserved_at IS NULL AND available_at <= :available_at
                 ORDER BY id ASC LIMIT 1 FOR UPDATE SKIP LOCKED",
            );
            $stmt->execute([':queue' => $queueName, ':available_at' => time()]);
            $rawJob = $stmt->fetch(PDO::FETCH_OBJ);

            if (!$rawJob) {
                $this->pdo->commit();
                return null;
            }

            // Đánh dấu job là đã được "đặt chỗ"
            $updateStmt = $this->pdo->prepare(
                "UPDATE {$this->table} SET reserved_at = :reserved_at, attempts = attempts + 1 WHERE id = :id",
            );
            $updateStmt->execute([':reserved_at' => time(), ':id' => $rawJob->id]);

            $this->pdo->commit();

            // Tái tạo job từ payload
            $payloadData = json_decode($rawJob->payload, true);
            $jobClass = $payloadData['job_class'] ?? null;
            if ($jobClass && class_exists($jobClass) && is_subclass_of($jobClass, Job::class)) {
                return new $jobClass($payloadData['data']);
            }

            return null;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    protected function pushToDatabase(string $payload, ?string $queue = null, int $delay = 0): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO {$this->table} (queue, payload, attempts, reserved_at, available_at, created_at)
             VALUES (:queue, :payload, 0, NULL, :available_at, :created_at)",
        );

        $stmt->execute([
            ':queue' => $this->getQueueName($queue),
            ':payload' => $payload,
            ':available_at' => time() + $delay,
            ':created_at' => time(),
        ]);
    }

    protected function createPayload(Job $job): string
    {
        $data = method_exists($job, 'getQueueableData') ? $job->getQueueableData() : (array) $job;

        return json_encode([
            'job_class' => get_class($job),
            'data'      => $data,
        ]);
    }

    protected function getQueueName(?string $queue): string
    {
        return $queue ?? $this->defaultQueue;
    }

    protected function getSeconds($delay): int
    {
        if ($delay instanceof DateTimeInterface) {
            return max(0, $delay->getTimestamp() - time());
        }
        return (int) $delay;
    }
}
