<?php

use App\Ai\Drivers\StubAiAgentDriver;

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
];
