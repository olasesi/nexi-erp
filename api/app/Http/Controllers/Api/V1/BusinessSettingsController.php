<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateBusinessSettingsRequest;
use App\Services\BusinessSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BusinessSettingsController extends Controller
{
    public function __construct(
        protected BusinessSettingsService $settings
    ) {}

    /**
     * All business settings groups plus navigation meta and options.
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);

        $options = [];

        foreach ($this->settings->meta() as $group => $_) {
            foreach (array_keys(config("business-settings.groups.{$group}.keys", [])) as $key) {
                $key = (string) $key;

                if (in_array(config("business-settings.groups.{$group}.keys.{$key}.type"), ['select', 'multi_select'], true)) {
                    $options[$group][$key] = $this->settings->optionsFor($group, $key);
                }
            }
        }

        return response()->json([
            'data' => $this->settings->values($companyId),
            'meta' => [
                'company_id' => $companyId,
                'groups' => $this->settings->meta(),
                'options' => $options,
            ],
        ]);
    }

    /**
     * A single business settings group.
     */
    public function show(Request $request, string $group): JsonResponse
    {
        if (config("business-settings.groups.{$group}") === null) {
            return response()->json(['message' => 'Settings group not found.'], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'data' => $this->settings->group($this->resolveCompanyId($request), $group),
        ]);
    }

    /**
     * Bulk-update a business settings group.
     */
    public function update(UpdateBusinessSettingsRequest $request, string $group): JsonResponse
    {
        if (config("business-settings.groups.{$group}") === null) {
            return response()->json(['message' => 'Settings group not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->settings->set($this->resolveCompanyId($request), $group, $request->validated());

        return response()->json([
            'message' => ucwords(str_replace('_', ' ', $group)).' settings updated successfully.',
            'data' => $this->settings->group($this->resolveCompanyId($request), $group),
        ]);
    }

    private function resolveCompanyId(Request $request): ?int
    {
        $requested = $request->input('company_id');

        if ($requested !== null && $requested !== '') {
            return (int) $requested;
        }

        return auth('api')->user()->company_id ?? null;
    }
}
