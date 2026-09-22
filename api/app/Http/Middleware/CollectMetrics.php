<?php

namespace App\Http\Middleware;

use App\Services\MetricsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CollectMetrics
{
    public function __construct(
        protected MetricsService $metrics
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $start = hrtime(true);

        $response = $next($request);

        $this->metrics->record(
            (hrtime(true) - $start) / 1000000000,
            $response->getStatusCode()
        );

        return $response;
    }
}
