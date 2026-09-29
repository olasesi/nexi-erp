<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiVersions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public discovery endpoint so a client can learn which versions exist before
 * it has to guess one.
 */
class ApiVersionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $versions = array_map(
            fn (string $version): array => ApiVersions::describe($version),
            ApiVersions::supported()
        );

        return response()->json([
            'data' => [
                'default' => ApiVersions::default(),
                'served' => (string) $request->attributes->get('api_version', ApiVersions::default()),
                'versions' => $versions,
            ],
        ]);
    }
}
