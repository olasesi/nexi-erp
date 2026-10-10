<?php

namespace App\Ai\Drivers;

use App\Contracts\AiAgentDriver;
use App\Models\AiAgentThread;

/**
 * Provider-agnostic local stub. Returns a deterministic reply so the agent
 * works offline, in tests and before a real provider is wired in.
 */
class StubAiAgentDriver implements AiAgentDriver
{
    public function reply(AiAgentThread $thread, string $message): string
    {
        return "I received your request: {$message}";
    }
}
