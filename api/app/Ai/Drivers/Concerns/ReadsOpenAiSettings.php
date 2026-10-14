<?php

namespace App\Ai\Drivers\Concerns;

use App\Services\BusinessSettingsService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Shared credential/HTTP plumbing for the OpenAI drivers. Credentials are read
 * from the company's business-integration settings (group `ai_assistant`,
 * `ai_document` or `ai_image`) and fall back to the `ai.providers.openai`
 * config, so a company can bring its own key while local/dev installs can use
 * an environment key.
 */
trait ReadsOpenAiSettings
{
    /**
     * @return array{api_key: string, base_url: string, model: string, system_prompt: string}
     */
    protected function openAiSettings(?int $companyId, string $group, string $defaultModel): array
    {
        $stored = $companyId !== null
            ? app(BusinessSettingsService::class)->group($companyId, $group)
            : [];

        return [
            'api_key' => $this->settingOr($stored, 'api_key', ''),
            'base_url' => rtrim($this->settingOr($stored, 'base_url', 'https://api.openai.com/v1'), '/'),
            'model' => $this->setting($stored, 'model', $defaultModel),
            'system_prompt' => $this->setting($stored, 'system_prompt', (string) config('ai.providers.openai.system_prompt', '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private function setting(array $stored, string $key, string $fallback): string
    {
        $value = $stored[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private function settingOr(array $stored, string $key, string $fallback): string
    {
        return $this->setting($stored, $key, (string) config("ai.providers.openai.{$key}", $fallback));
    }

    /**
     * @param  array{api_key: string, base_url: string}  $settings
     */
    protected function openAiClient(array $settings): PendingRequest
    {
        if (($settings['api_key'] ?? '') === '') {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        return Http::withToken($settings['api_key'])
            ->baseUrl($settings['base_url'])
            ->acceptJson()
            ->timeout((int) config('ai.timeout', 30));
    }
}
