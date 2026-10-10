<?php

namespace App\Services;

use App\Ai\Drivers\StubAiAgentDriver;
use App\Contracts\AiAgentDriver;
use App\Models\AiAgentThread;

/**
 * Resolves the configured AiAgentDriver once and delegates replies to it, so
 * controllers never know which provider is wired in.
 */
class AiAgentService
{
    private AiAgentDriver $driver;

    public function __construct()
    {
        $key = (string) config('ai.driver', 'stub');
        $class = config("ai.drivers.{$key}");

        $this->driver = $class ? app($class) : app(StubAiAgentDriver::class);
    }

    public function reply(AiAgentThread $thread, string $message): string
    {
        return $this->driver->reply($thread, $message);
    }
}
