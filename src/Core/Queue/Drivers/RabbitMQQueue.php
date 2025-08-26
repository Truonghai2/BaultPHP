<?php

namespace Core\Queue\Drivers;

use Core\Contracts\Queue\Job;
use Core\Contracts\Queue\Queue as QueueContract;
use InvalidArgumentException;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQQueue implements QueueContract
{
    protected AMQPStreamConnection $connection;
    protected AMQPChannel $channel;
    protected string $defaultQueue;
    protected array $exchangeOptions;

    public function __construct(AMQPStreamConnection $connection, string $defaultQueue, array $exchangeOptions)
    {
        $this->connection = $connection;
        $this->defaultQueue = $defaultQueue;
        $this->exchangeOptions = $exchangeOptions;
        $this->channel = $this->connection->channel();
        $this->declareStructure();
    }

    /**
     * Push a new job onto the queue.
     *
     * @param  string|object  $job
     * @param  mixed   $data
     * @param  string|null  $queue
     * @return mixed
     */
    public function push($job, $data = '', $queue = null): void
    {
        $queueName = $queue ?? $this->defaultQueue;
        $payload = $this->createPayload($job, $data);

        $message = new AMQPMessage($payload, [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ]);

        $this->channel->basic_publish(
            $message,
            $this->exchangeOptions['name'] ?? '',
            $queueName,
        );
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param  string|null  $queue
     * @return array|null
     */
    public function pop(?string $queue = null): ?Job
    {
        $queueName = $queue ?? $this->defaultQueue;

        $message = $this->channel->basic_get($queueName);

        if ($message) {
            return new Job($this->channel, $message, $queueName);
        }

        return null;
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @param  string|object  $job
     * @param  mixed   $data
     * @param  string|null  $queue
     * @return mixed
     */
    public function later($delay, $job, $data = '', $queue = null): void
    {
        $queueName = $queue ?? $this->defaultQueue;
        $payload = $this->createPayload($job, $data);

        // Calculate delay in milliseconds
        $delayInMilliseconds = $this->getSeconds($delay) * 1000;

        // Declare a temporary delayed queue and exchange if not already declared
        $delayedQueueName = $queueName . '_delayed_' . $delayInMilliseconds;
        $delayedExchangeName = $queueName . '_delayed_exchange';

        // Declare delayed exchange (type fanout or direct, depending on routing needs)
        $this->channel->exchange_declare(
            $delayedExchangeName,
            'direct',
            false,
            true, // Durable
            false,
        );

        // Declare delayed queue with TTL and Dead Letter Exchange
        $this->channel->queue_declare(
            $delayedQueueName,
            false,
            true, // Durable
            false,
            false,
            false,
            [
                'x-dead-letter-exchange' => ['S', $this->exchangeOptions['name'] ?? ''], // Route to main exchange
                'x-dead-letter-routing-key' => ['S', $queueName], // Route to main queue
                'x-message-ttl' => ['I', $delayInMilliseconds],
            ],
        );

        // Bind delayed queue to delayed exchange
        $this->channel->queue_bind(
            $delayedQueueName,
            $delayedExchangeName,
            $queueName, // Routing key for delayed messages
        );

        $message = new AMQPMessage($payload, [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ]);

        $this->channel->basic_publish(
            $message,
            $delayedExchangeName, // Publish to the delayed exchange
            $queueName, // Routing key for delayed messages
        );
    }

    /**
     * Release a reserved job back onto the queue.
     *
     * @param  string  $queue
     * @param  \Core\Queue\Job  $job
     * @param  bool  $requeue
     * @return void
     */
    public function release($queue, Job $job, $requeue = true): void
    {
        $job->release($requeue);
    }

    /**
     * Delete a reserved job from the queue.
     *
     * @param  string  $queue
     * @param  \Core\Queue\Job  $job
     * @return void
     */
    public function delete($queue, Job $job): void
    {
        $job->ack();
    }

    /**
     * Get the number of seconds from a delay.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @return int
     */
    protected function getSeconds($delay): int
    {
        if ($delay instanceof \DateInterval) {
            $delay = (new \DateTime())->add($delay)->getTimestamp() - (new \DateTime())->getTimestamp();
        }

        return $delay;
    }

    /**
     * Create a payload string from the given job and data.
     *
     * @param  string|object  $job
     * @param  mixed  $data
     * @return string
     */
    protected function createPayload($job, $data): string
    {
        // Trong một ứng dụng thực tế, payload này sẽ chứa thông tin về class của job
        // để worker có thể khởi tạo và thực thi nó.
        $payload = json_encode(['job' => serialize($job), 'data' => $data]);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Unable to create queue payload: ' . json_last_error_msg());
        }

        return $payload;
    }

    /**
     * Declare the necessary queue and exchange.
     */
    protected function declareStructure(): void
    {
        $this->channel->exchange_declare(
            $this->exchangeOptions['name'] ?? '',
            $this->exchangeOptions['type'] ?? 'direct',
            $this->exchangeOptions['passive'] ?? false,
            $this->exchangeOptions['durable'] ?? true,
            $this->exchangeOptions['auto_delete'] ?? false,
        );

        $this->channel->queue_declare(
            $this->defaultQueue,
            false,
            true,
            false,
            false,
        );

        $this->channel->queue_bind(
            $this->defaultQueue,
            $this->exchangeOptions['name'] ?? '',
            $this->defaultQueue,
        );
    }

    public function __destruct()
    {
        $this->channel->close();
        $this->connection->close();
    }
}
