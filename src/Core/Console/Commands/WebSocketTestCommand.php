<?php

namespace Core\Console\Commands;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\WebSocket\WebSocketManager;
use InvalidArgumentException;
use Throwable;

class WebSocketTestCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    /**
     * The signature of the command.
     */
    public function signature(): string
    {
        return 'websocket:test 
                {--user=* : The user ID(s) to send the message to.} 
                {--broadcast : Broadcast the message to all connected clients.} 
                {--message= : The JSON message to send (e.g., \'{"event":"test","data":"hello"}\').}';
    }

    /**
     * The description of the command.
     */
    public function description(): string
    {
        return 'Send a test message through the WebSocket server.';
    }

    /**
     * The handler for the command.
     */
    public function handle(): int
    {
        $this->comment('Sending WebSocket test message...');

        try {
            /** @var WebSocketManager $manager */
            $manager = $this->app->make(WebSocketManager::class);

            $userIds = $this->option('user');
            $isBroadcast = $this->option('broadcast');
            $messageJson = $this->option('message');

            if (empty($userIds) && !$isBroadcast) {
                throw new InvalidArgumentException('You must specify at least one --user or use the --broadcast flag.');
            }

            if (empty($messageJson)) {
                throw new InvalidArgumentException('The --message option is required and cannot be empty.');
            }

            $payload = json_decode($messageJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException('The --message option must be a valid JSON string. Error: ' . json_last_error_msg());
            }

            if ($isBroadcast) {
                $manager->broadcast($payload);
                $this->info('✔ Broadcast message sent successfully!');
            } else {
                $manager->sendToUser($userIds, $payload);
                $this->info('✔ Message sent successfully to user(s): ' . implode(', ', $userIds));
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
