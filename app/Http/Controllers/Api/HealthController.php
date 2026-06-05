<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $status = 'healthy';
        $checks = [];

        // Database check
        try {
            DB::connection()->getPdo();
            $checks['database'] = 'connected';
        } catch (\Exception $e) {
            $checks['database'] = 'error: '.$e->getMessage();
            $status = 'unhealthy';
        }

        // Cache check
        try {
            Cache::store()->has('health-check');
            $checks['cache'] = 'responsive';
        } catch (\Exception $e) {
            $checks['cache'] = 'error: '.$e->getMessage();
            $status = 'degraded';
        }

        // Storage check
        try {
            Storage::disk('local')->exists('/');
            $checks['storage'] = 'accessible';
        } catch (\Exception $e) {
            $checks['storage'] = 'error: '.$e->getMessage();
            $status = 'degraded';
        }

        return response()->json([
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'service' => 'nexi-erp',
            'version' => config('app.version', '1.0.0'),
            'checks' => $checks,
        ]);
    }
}
