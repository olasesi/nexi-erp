<?php

use App\Ai\Drivers\OpenAiAgentDriver;
use App\Ai\Drivers\OpenAiDocumentDriver;
use App\Ai\Drivers\OpenAiImageDriver;
use App\Ai\Drivers\StubAiAgentDriver;
use App\Ai\Drivers\StubAiDocumentDriver;
use App\Ai\Drivers\StubAiImageDriver;

return [
    /*
    |--------------------------------------------------------------------------
    | Provider credentials
    |--------------------------------------------------------------------------
    | Defaults used when a company has not stored its own key in the matching
    | business-integration settings group. Keep the keys out of source and set
    | them through the environment in production.
    */
    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'image_model' => env('OPENAI_IMAGE_MODEL', 'dall-e-3'),
            'system_prompt' => env('OPENAI_SYSTEM_PROMPT'),
        ],
    ],

    'timeout' => (int) env('AI_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | AI Agent Driver
    |--------------------------------------------------------------------------
    | The active driver key resolved from the `drivers` map below. Defaults to
    | the deterministic local stub so the add-on never depends on a remote
    | provider being configured.
    */
    'driver' => env('AI_AGENT_DRIVER', 'stub'),

    'drivers' => [
        'stub' => StubAiAgentDriver::class,
        'openai' => OpenAiAgentDriver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Document Driver
    |--------------------------------------------------------------------------
    | Resolves the driver used to draft or rewrite long-form documents. The
    | stub keeps generation deterministic and offline.
    */
    'document_driver' => env('AI_DOCUMENT_DRIVER', 'stub'),

    'document_drivers' => [
        'stub' => StubAiDocumentDriver::class,
        'openai' => OpenAiDocumentDriver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Image Driver
    |--------------------------------------------------------------------------
    | Resolves the driver used to render images from a prompt. The stub returns
    | a deterministic inline SVG data URI so the add-on never calls out.
    */
    'image_driver' => env('AI_IMAGE_DRIVER', 'stub'),

    'image_drivers' => [
        'stub' => StubAiImageDriver::class,
        'openai' => OpenAiImageDriver::class,
    ],
];
