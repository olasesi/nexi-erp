<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateIntegrationRequest;
use App\Services\IntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class IntegrationController extends Controller
{
    public function __construct(
        protected IntegrationService $integrations
    ) {}

    /**
     * Every catalogued provider with this company's connection state.
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);

        return response()->json([
            'data' => $this->integrations->catalog($companyId),
            'meta' => [
                'company_id' => $companyId,
                'categories' => $this->integrations->categories(),
            ],
        ]);
    }

    /**
     * A single provider with its fields (secrets masked) and connection state.
     */
    public function show(Request $request, string $provider): JsonResponse
    {
        $detail = $this->integrations->show($this->resolveCompanyId($request), $provider);

        if ($detail === null) {
            return $this->notFound($provider);
        }

        return response()->json(['data' => $detail]);
    }

    /**
     * Save credentials (and optionally the enabled flag) for a provider.
     */
    public function update(UpdateIntegrationRequest $request, string $provider): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);

        if (! $this->integrations->exists($provider)) {
            return $this->notFound($provider);
        }

        $detail = $this->integrations->update($companyId, $provider, $request->validated());

        if ($detail === null) {
            return $this->notFound($provider);
        }

        return response()->json([
            'message' => "{$detail['label']} settings saved successfully.",
            'data' => $detail,
        ]);
    }

    /**
     * Enable a provider, refusing when its credentials are incomplete.
     */
    public function connect(Request $request, string $provider): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);
        $detail = $this->integrations->show($companyId, $provider);

        if ($detail === null) {
            return $this->notFound($provider);
        }

        if ($detail['configured'] !== true) {
            return response()->json([
                'message' => "The {$detail['label']} integration is not fully configured.",
                'missing_fields' => $detail['missing_fields'],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $connected = $this->integrations->connect($companyId, $provider);

        if ($connected === null) {
            return $this->notFound($provider);
        }

        return response()->json([
            'message' => "{$connected['label']} connected successfully.",
            'data' => $connected,
        ]);
    }

    /**
     * Disable a provider while keeping its stored credentials.
     */
    public function disconnect(Request $request, string $provider): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);
        $detail = $this->integrations->show($companyId, $provider);

        if ($detail === null) {
            return $this->notFound($provider);
        }

        $disconnected = $this->integrations->disconnect($companyId, $provider);

        if ($disconnected === null) {
            return $this->notFound($provider);
        }

        return response()->json([
            'message' => "{$disconnected['label']} disconnected successfully.",
            'data' => $disconnected,
        ]);
    }

    /**
     * Record a connection test for a connected provider.
     */
    public function test(Request $request, string $provider): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);
        $detail = $this->integrations->show($companyId, $provider);

        if ($detail === null) {
            return $this->notFound($provider);
        }

        $tested = $this->integrations->test($companyId, $provider);

        if ($tested === null) {
            return response()->json([
                'message' => "The {$detail['label']} integration is not connected.",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' => "{$tested['label']} connection test succeeded.",
            'data' => $tested,
        ]);
    }

    private function notFound(string $provider): JsonResponse
    {
        return response()->json([
            'message' => "Integration [{$provider}] not found.",
        ], Response::HTTP_NOT_FOUND);
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
