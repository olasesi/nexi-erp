<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HealthCheckService;
use App\Support\ApiVersions;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class HealthController extends Controller
{
    public function __invoke(HealthCheckService $health): JsonResponse
    {
        $report = $health->report();

        $status = $report['status'] === HealthCheckService::STATUS_UNHEALTHY
            ? Response::HTTP_SERVICE_UNAVAILABLE
            : Response::HTTP_OK;

        return response()->json([
            'status' => $report['status'],
            'service' => 'nexi-erp',
            'version' => config('app.version', '1.0.0'),
            'api_version' => ApiVersions::default(),
            'timestamp' => $report['checked_at'],
            'checks' => $report['checks'],
        ], $status);
    }
}
