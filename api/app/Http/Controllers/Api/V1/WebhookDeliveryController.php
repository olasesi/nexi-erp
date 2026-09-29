<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WebhookDeliveryResource;
use App\Models\WebhookDelivery;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;

class WebhookDeliveryController extends Controller
{
    public function index(): JsonResponse
    {
        $query = WebhookDelivery::query()->orderByDesc('id');

        if ($endpointId = request('webhook_endpoint_id')) {
            $query->where('webhook_endpoint_id', $endpointId);
        }

        if ($status = request('status')) {
            $query->where('status', $status);
        }

        if ($event = request('event')) {
            $query->where('event', $event);
        }

        $perPage = request()->integer('per_page', 15);
        $items = $query->paginate(min($perPage, 100));

        return WebhookDeliveryResource::collection($items)->response();
    }

    public function show(int $id): JsonResponse
    {
        return (new WebhookDeliveryResource(WebhookDelivery::findOrFail($id)))->response();
    }

    /**
     * Replay a delivery against its endpoint, keeping the previous attempt
     * history in the response.
     */
    public function redeliver(int $id): JsonResponse
    {
        $delivery = WebhookDelivery::findOrFail($id);

        if ($delivery->status === WebhookDelivery::STATUS_SUCCEEDED) {
            return response()->json([
                'message' => 'This delivery has already succeeded.',
            ], 422);
        }

        $delivery->forceFill([
            'status' => WebhookDelivery::STATUS_PENDING,
            'next_attempt_at' => null,
        ])->save();

        app(WebhookService::class)->attempt($delivery);

        return (new WebhookDeliveryResource($delivery->refresh()))->response();
    }
}
