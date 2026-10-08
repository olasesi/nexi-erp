<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The four Smart Dashboard tabs. Each one is a read-only aggregate behind
 * the smart-dashboard add-on and the shared analytics.view permission; the
 * heavy lifting (and caching) lives in AnalyticsService.
 */
class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    public function executive(Request $request): JsonResponse
    {
        return $this->tab($request, 'executive');
    }

    public function financial(Request $request): JsonResponse
    {
        return $this->tab($request, 'financial');
    }

    public function sales(Request $request): JsonResponse
    {
        return $this->tab($request, 'sales');
    }

    public function operations(Request $request): JsonResponse
    {
        return $this->tab($request, 'operations');
    }

    /**
     * Shared validation and envelope for every tab: a company, an optional
     * list limit and an optional cache bypass.
     *
     * @param  'executive'|'financial'|'sales'|'operations'  $tab
     */
    private function tab(Request $request, string $tab): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('analytics.max_limit', 100)],
            'fresh' => 'nullable|boolean',
        ]);

        $payload = $this->analytics->tab($tab, (int) $data['company_id'], [
            'limit' => isset($data['limit']) ? (int) $data['limit'] : null,
            'fresh' => (bool) ($data['fresh'] ?? false),
        ]);

        return response()->json($payload);
    }
}
