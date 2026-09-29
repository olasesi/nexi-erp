<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCurrencyRateRequest;
use App\Http\Requests\Api\V1\UpdateCurrencyRateRequest;
use App\Http\Resources\CurrencyRateResource;
use App\Models\CurrencyRate;
use App\Services\CurrencyService;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CurrencyRateController extends Controller
{
    public function index(): JsonResponse
    {
        $query = CurrencyRate::query();

        if ($companyId = request('company_id')) {
            $query->where('company_id', $companyId);
        }

        $perPage = request()->integer('per_page', 15);
        $items = $query->paginate(min($perPage, 100));

        return CurrencyRateResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $data = app(StoreCurrencyRateRequest::class)->validated();

        $data['company_id'] = $data['company_id'] ?? auth('api')->user()?->company_id;
        $data['base_currency'] = strtoupper($data['base_currency'] ?? app(CurrencyService::class)->baseCurrency($data['company_id']));

        $rate = DB::transaction(function () use ($data) {
            return CurrencyRate::updateOrCreate(
                ['company_id' => $data['company_id'], 'base_currency' => $data['base_currency'], 'currency' => strtoupper($data['currency'])],
                ['rate' => $data['rate']]
            );
        });

        return (new CurrencyRateResource($rate))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        return (new CurrencyRateResource(CurrencyRate::findOrFail($id)))->response();
    }

    public function update(int $id): JsonResponse
    {
        $item = CurrencyRate::findOrFail($id);
        $item->update(app(UpdateCurrencyRateRequest::class)->validated());

        return (new CurrencyRateResource($item->fresh()))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        CurrencyRate::findOrFail($id)->delete();

        return response()->json(null, 204);
    }

    public function restore(int $id): JsonResponse
    {
        $item = CurrencyRate::query()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->findOrFail($id);

        if ($item->getAttribute('deleted_at') === null) {
            return response()->json([
                'message' => 'This record is not trashed.',
            ], 422);
        }

        $item->forceFill(['deleted_at' => null])->save();

        return (new CurrencyRateResource($item->fresh()))->response();
    }
}
