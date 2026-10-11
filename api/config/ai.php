<?php

use App\Ai\Drivers\StubAiAgentDriver;
use App\Ai\Drivers\StubAiDocumentDriver;
use App\Ai\Drivers\StubAiImageDriver;

return [
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
    ],
];
