<?php

namespace App\Ai\Drivers;

use App\Contracts\AiImageDriver;

/**
 * Provider-agnostic local stub. Renders a deterministic inline SVG data URI so
 * AI Image works offline, in tests and before a real provider is wired in.
 */
class StubAiImageDriver implements AiImageDriver
{
    /**
     * @param  array{negative_prompt?: string|null, style?: string|null, size?: string|null}  $options
     * @return array{url: string, provider: string, model: string|null}
     */
    public function generate(string $prompt, array $options = []): array
    {
        [$width, $height] = $this->dimensions($options['size'] ?? null);

        $label = htmlspecialchars($this->truncate($prompt), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $bg = '#1e293b';
        $fg = '#e2e8f0';

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%2$d" viewBox="0 0 %1$d %2$d">'
            .'<rect width="%1$d" height="%2$d" fill="%3$s"/>'
            .'<text x="50%%" y="50%%" fill="%4$s" font-family="monospace" font-size="24" '
            .'text-anchor="middle" dominant-baseline="middle">%5$s</text></svg>',
            $width,
            $height,
            $bg,
            $fg,
            $label,
        );

        return [
            'url' => 'data:image/svg+xml;base64,'.base64_encode($svg),
            'provider' => 'stub',
            'model' => 'stub-image-1',
        ];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function dimensions(?string $size): array
    {
        if ($size !== null && preg_match('/^(\d+)x(\d+)$/', $size, $matches) === 1) {
            return [(int) $matches[1], (int) $matches[2]];
        }

        return [512, 512];
    }

    private function truncate(string $value, int $limit = 48): string
    {
        return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit).'…' : $value;
    }
}
