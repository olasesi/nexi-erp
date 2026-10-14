<?php

namespace App\Ai\Drivers;

use App\Ai\Drivers\Concerns\ReadsOpenAiSettings;
use App\Contracts\AiImageDriver;
use RuntimeException;

/**
 * OpenAI (Images) driver for AI Image. Requests one image for the prompt and
 * returns its hosted URL (or an inline data URI when the API returns base64).
 */
class OpenAiImageDriver implements AiImageDriver
{
    use ReadsOpenAiSettings;

    /**
     * @param  array{negative_prompt?: string|null, style?: string|null, size?: string|null, company_id?: int|null}  $options
     * @return array{url: string, provider: string, model: string|null}
     */
    public function generate(string $prompt, array $options = []): array
    {
        $companyId = isset($options['company_id']) ? (int) $options['company_id'] : null;

        $settings = $this->openAiSettings(
            $companyId,
            'ai_image',
            (string) config('ai.providers.openai.image_model', 'dall-e-3'),
        );

        $payload = [
            'model' => $settings['model'],
            'prompt' => $this->prompt($prompt, $options),
            'n' => 1,
            'size' => $this->size($options['size'] ?? null),
        ];

        $response = $this->openAiClient($settings)->post('images/generations', $payload);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI image request failed: '.$response->body());
        }

        $url = $response->json('data.0.url');

        if (! is_string($url) || $url === '') {
            $base64 = $response->json('data.0.b64_json');
            $url = is_string($base64) && $base64 !== ''
                ? 'data:image/png;base64,'.$base64
                : '';
        }

        if ($url === '') {
            throw new RuntimeException('OpenAI did not return an image.');
        }

        return [
            'url' => $url,
            'provider' => 'openai',
            'model' => $settings['model'],
        ];
    }

    /**
     * @param  array{negative_prompt?: string|null, style?: string|null}  $options
     */
    private function prompt(string $prompt, array $options): string
    {
        $text = $prompt;

        if (is_string($options['style'] ?? null) && $options['style'] !== '') {
            $text .= '. Style: '.$options['style'];
        }

        if (is_string($options['negative_prompt'] ?? null) && $options['negative_prompt'] !== '') {
            $text .= '. Avoid: '.$options['negative_prompt'];
        }

        return $text;
    }

    private function size(?string $size): string
    {
        $allowed = ['256x256', '512x512', '1024x1024', '1024x1792', '1792x1024'];

        return is_string($size) && in_array($size, $allowed, true) ? $size : '1024x1024';
    }
}
