<?php

namespace App\Ai\Drivers;

use App\Contracts\AiDocumentDriver;

/**
 * Provider-agnostic local stub. Returns a deterministic body so AI Document
 * works offline, in tests and before a real provider is wired in.
 */
class StubAiDocumentDriver implements AiDocumentDriver
{
    /**
     * @param  array{title?: string|null, template?: string|null, language?: string|null}  $options
     * @return array{content: string, provider: string, model: string|null, tokens: int|null}
     */
    public function generate(string $prompt, array $options = []): array
    {
        $title = $options['title'] ?? null;

        $heading = $title !== null && $title !== ''
            ? "# {$title}\n\n"
            : '';

        $content = $heading.$prompt;

        return [
            'content' => $content,
            'provider' => 'stub',
            'model' => 'stub-document-1',
            'tokens' => str_word_count($content),
        ];
    }
}
