<?php

namespace App\Services;

use App\Ai\Drivers\StubAiDocumentDriver;
use App\Contracts\AiDocumentDriver;
use App\Models\AiDocument;

/**
 * Resolves the configured AiDocumentDriver once and drafts documents through
 * it, so controllers never know which provider is wired in.
 */
class AiDocumentService
{
    private AiDocumentDriver $driver;

    public function __construct()
    {
        $key = (string) config('ai.document_driver', 'stub');
        $class = config("ai.document_drivers.{$key}");

        $this->driver = $class ? app($class) : app(StubAiDocumentDriver::class);
    }

    /**
     * Draft the document body and stamp the generation metadata onto it.
     */
    public function generate(AiDocument $document): AiDocument
    {
        $result = $this->driver->generate($document->prompt, [
            'title' => $document->title,
            'template' => $document->template,
            'language' => $document->language,
            'company_id' => $document->company_id,
        ]);

        $document->forceFill([
            'content' => $result['content'],
            'provider' => $result['provider'],
            'model' => $result['model'],
            'tokens' => $result['tokens'],
            'status' => AiDocument::STATUS_GENERATED,
            'generated_at' => now(),
        ])->save();

        return $document;
    }
}
