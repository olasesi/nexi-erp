<?php

namespace App\Contracts;

use App\Models\AiAgentThread;

interface AiAgentDriver
{
    /**
     * Produce the assistant's reply for a message on the given thread.
     */
    public function reply(AiAgentThread $thread, string $message): string;
}
