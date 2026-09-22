<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MetricsService;
use Illuminate\Http\Response;

class MetricsController extends Controller
{
    public function __invoke(MetricsService $metrics): Response
    {
        return response($metrics->exposition(), Response::HTTP_OK, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }
}
