<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\BusinessHealthSnapshotResource;
use App\Models\BusinessHealthSnapshot;
use App\Services\BusinessHealthService;
use Illuminate\Http\JsonResponse;

/**
 * Read surface for the AI Business Advisor add-on. The report is computed
 * live from the company's own data; the snapshot history is whatever the
 * nightly `advisor:snapshot` run has stored.
 */
class BusinessAdvisorController extends BaseController
{
    public function __construct(private readonly BusinessHealthService $advisor) {}

    /**
     * GET /api/v1/business-advisor/report — the live scorecard.
     */
    public function index(): JsonResponse
    {
        $companyId = $this->companyId();

        if ($companyId === null) {
            return $this->noCompany();
        }

        return response()->json($this->advisor->report($companyId));
    }

    /**
     * GET /api/v1/business-advisor/snapshots — stored daily scores for the
     * trend line.
     */
    public function snapshots(): JsonResponse
    {
        $companyId = $this->companyId();

        if ($companyId === null) {
            return $this->noCompany();
        }

        $perPage = request()->integer('per_page', 15);
        $items = BusinessHealthSnapshot::where('company_id', $companyId)
            ->recent()
            ->paginate(min($perPage, 100));

        return BusinessHealthSnapshotResource::collection($items)->response();
    }

    private function companyId(): ?int
    {
        return auth('api')->user()?->company_id;
    }

    private function noCompany(): JsonResponse
    {
        return response()->json([
            'message' => 'Your account is not attached to a company.',
        ], 422);
    }
}
