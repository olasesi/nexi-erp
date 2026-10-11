<?php

namespace App\Contracts;

interface AiDocumentDriver
{
    /**
     * Produce a document body from a prompt. Implementations own any provider
     * specific concerns; the caller only sees the normalised result.
     *
     * @param  array{title?: string|null, template?: string|null, language?: string|null}  $options
     * @return array{content: string, provider: string, model: string|null, tokens: int|null}
     */
    public function generate(string $prompt, array $options = []): array;
}
