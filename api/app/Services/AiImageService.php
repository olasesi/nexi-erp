<?php

namespace App\Services;

use App\Ai\Drivers\StubAiImageDriver;
use App\Contracts\AiImageDriver;
use App\Models\AiImage;

/**
 * Resolves the configured AiImageDriver once and renders images through it, so
 * controllers never know which provider is wired in.
 */
class AiImageService
{
    private AiImageDriver $driver;

    public function __construct()
    {
        $key = (string) config('ai.image_driver', 'stub');
        $class = config("ai.image_drivers.{$key}");

        $this->driver = $class ? app($class) : app(StubAiImageDriver::class);
    }

    /**
     * Render the image and stamp the generation metadata onto it.
     */
    public function generate(AiImage $image): AiImage
    {
        $result = $this->driver->generate($image->prompt, [
            'negative_prompt' => $image->negative_prompt,
            'style' => $image->style,
            'size' => $image->size,
        ]);

        $image->forceFill([
            'url' => $result['url'],
            'provider' => $result['provider'],
            'model' => $result['model'],
            'status' => AiImage::STATUS_GENERATED,
            'generated_at' => now(),
        ])->save();

        return $image;
    }
}
