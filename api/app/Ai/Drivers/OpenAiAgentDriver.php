<?php

namespace App\Ai\Drivers;

use App\Ai\Drivers\Concerns\ReadsOpenAiSettings;
use App\Contracts\AiAgentDriver;
use App\Models\AiAgentThread;
use RuntimeException;

/**
 * OpenAI (Chat Completions) driver for the AI Agent. Sends the thread's
 * transcript as the conversation and returns the assistant's reply.
 */
class OpenAiAgentDriver implements AiAgentDriver
{
    use ReadsOpenAiSettings;

    public function reply(AiAgentThread $thread, string $message): string
    {
        $settings = $this->openAiSettings(
            (int) $thread->company_id,
            'ai_assistant',
            (string) config('ai.providers.openai.model', 'gpt-4o-mini'),
        );

        $response = $this->openAiClient($settings)->post('chat/completions', [
            'model' => $settings['model'],
            'messages' => $this->transcript($thread, $message, $settings['system_prompt']),
        ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI agent request failed: '.$response->body());
        }

        $content = $response->json('choices.0.message.content');

        return is_string($content) ? trim($content) : '';
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function transcript(AiAgentThread $thread, string $message, string $systemPrompt): array
    {
        $messages = [];

        if ($systemPrompt !== '') {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        } elseif (is_string($thread->context) && $thread->context !== '') {
            $messages[] = ['role' => 'system', 'content' => $thread->context];
        }

        foreach ($thread->messages()->orderBy('id')->get() as $stored) {
            $messages[] = ['role' => $stored->role, 'content' => (string) $stored->body];
        }

        $last = end($messages);

        if ($last === false || $last['content'] !== $message) {
            $messages[] = ['role' => 'user', 'content' => $message];
        }

        return $messages;
    }
}
