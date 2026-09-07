<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrenciesController extends Controller
{
    /**
     * Return the supported ISO-4217 currency list. The default currency is
     * always listed first, mirroring the configuration surface popularised
     * by the reference DASH SaaS demo.
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim(strtolower((string) $request->query('search')));

        $currencies = collect(config('currencies'))
            ->sortBy(fn ($c) => $c[4] ? 0 : 1)
            ->values()
            ->map(fn (array $c) => [
                'id' => $c[0],
                'name' => $c[1],
                'code' => $c[2],
                'symbol' => $c[3],
                'is_default' => $c[4],
            ])
            ->when($search !== '', fn ($list) => $list->filter(fn ($c) => (
                str_contains(strtolower($c['name']), $search)
                || str_contains(strtolower($c['code']), $search)
            ))->values())
            ->all();

        return response()->json(['data' => $currencies, 'meta' => ['total' => count($currencies)]]);
    }
}
