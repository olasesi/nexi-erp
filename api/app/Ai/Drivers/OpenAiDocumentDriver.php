<?php

namespace App\Ai\Drivers;

use App\Ai\Drivers\Concerns\ReadsOpenAiSettings;
use App\Contracts\AiDocumentDriver;
use RuntimeException;

/**
 * OpenAI (Chat Completions) driver for AI Document. Turns the prompt (plus any
 * title/template/language hints) into a drafted body.
 */
class OpenAiDocumentDriver implements AiDocumentDriver
{
    use ReadsOpenAiSettings;

    /**
     * @param  array{title?: string|null, template?: string|null, language?: string|null, company_id?: int|null}  $options
     * @return array{content: string, provider: string, model: string|null, tokens: int|null}
     */
    public function generate(string $prompt, array $options = []): array
    {
        $companyId = isset($options['company_id']) ? (int) $options['company_id'] : null;

        $settings = $this->openAiSettings(
            $companyId,
            'ai_document',
            (string) config('ai.providers.openai.model', 'gpt-4o-mini'),
        );

        $response = $this->openAiClient($settings)->post('chat/completions', [
            'model' => $settings['model'],
            'messages' => [
                ['role' => 'system', 'content' => $this->systemPrompt($settings['system_prompt'])],
                ['role' => 'user', 'content' => $this->instruction($prompt, $options)],
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI document request failed: '.$response->body());
        }

        $content = $response->json('choices.0.message.content');
        $tokens = $response->json('usage.total_tokens');

        return [
            'content' => is_string($content) ? trim($content) : '',
            'provider' => 'openai',
            'model' => $settings['model'],
            'tokens' => is_int($tokens) ? $tokens : null,
        ];
    }

    private function systemPrompt(string $configured): string
    {
        return $configured !== ''
            ? $configured
            : 'You are a professional business writer. Draft clear, well-structured documents.';
    }

    /**
     * @param  array{title?: string|null, template?: string|null, language?: string|null}  $options
     */
    private function instruction(string $prompt, array $options): string
    {
        $lines = [];

        if (is_string($options['title'] ?? null) && $options['title'] !== '') {
            $lines[] = 'Title: '.$options['title'];
        }

        if (is_string($options['template'] ?? null) && $options['template'] !== '') {
            $lines[] = 'Use this structure as a guide: '.$options['template'];
        }

        if (is_string($options['language'] ?? null) && $options['language'] !== '') {
            $lines[] = 'Write the document in: '.$options['language'];
        }

        $lines[] = $prompt;

        return implode("\n\n", $lines);
    }
}
