<?php

namespace App\Contracts;

interface AiImageDriver
{
    /**
     * Render an image from a prompt. Implementations own any provider specific
     * concerns; the caller only sees the normalised result.
     *
     * @param  array{negative_prompt?: string|null, style?: string|null, size?: string|null}  $options
     * @return array{url: string, provider: string, model: string|null}
     */
    public function generate(string $prompt, array $options = []): array;
}
