<?php

namespace Core\Realtime;

use Illuminate\Support\Facades\Redis;

/**
 * RealtimeBroadcaster handles real-time broadcasting of events to users and systems.
 * It uses Redis for publishing messages to specific channels.
 */
class RealtimeBroadcaster
{

    public function user(int $userId, array $data): void
    {
        $this->emit("user.{$userId}", $data);
    }

    public function system(string $type, array $data): void
    {
        $this->emit("system.{$type}", $data);
    }

    public function emit(string $channel, array $payload): void
    {
        Redis::publish($channel, json_encode($payload));
    }
}
