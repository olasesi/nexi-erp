<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\UpdateAddonRequest;
use App\Services\AddonService;
use Illuminate\Http\JsonResponse;

/**
 * Catalog + per-company toggle for installable add-ons. The list is always
 * readable (the SPA needs it to decide what to show in the sidebar); only the
 * toggle write is gated, on addons.update.
 */
class AddonController extends BaseController
{
    public function __construct(private readonly AddonService $addons) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->addons->status(auth('api')->user()?->company_id),
        ]);
    }

    public function toggle(UpdateAddonRequest $request, string $addon): JsonResponse
    {
        if ($this->addons->definition($addon) === null) {
            return response()->json(['message' => 'Add-on not found.'], 404);
        }

        $enabled = (bool) $request->validated('enabled');

        $this->addons->set($addon, $enabled, auth('api')->user()?->company_id);

        return response()->json([
            'data' => [
                'id' => $addon,
                'enabled' => $enabled,
            ],
        ]);
    }
}
