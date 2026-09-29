<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWebhookEndpointRequest;
use App\Http\Requests\Api\V1\UpdateWebhookEndpointRequest;
use App\Http\Resources\WebhookDeliveryResource;
use App\Http\Resources\WebhookEndpointResource;
use App\Models\WebhookEndpoint;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class WebhookEndpointController extends Controller
{
    /**
     * Subscribable event catalogue, so clients can render a picker without
     * hardcoding event names.
     */
    public function events(): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                fn (string $event): array => [
                    'name' => $event,
                    'group' => str_contains($event, '.') ? explode('.', $event)[0] : 'other',
                ],
                WebhookService::events(),
            ),
        ]);
    }

    public function index(): JsonResponse
    {
        $query = WebhookEndpoint::query()->withCount('deliveries')->orderByDesc('id');

        if ($companyId = request('company_id')) {
            $query->where('company_id', $companyId);
        }

        if (request()->has('is_active')) {
            $query->where('is_active', request()->boolean('is_active'));
        }

        $perPage = request()->integer('per_page', 15);
        $items = $query->paginate(min($perPage, 100));

        return WebhookEndpointResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $data = app(StoreWebhookEndpointRequest::class)->validated();

        $endpoint = WebhookEndpoint::create([
            ...$data,
            'company_id' => $data['company_id'] ?? auth('api')->user()?->company_id,
            'secret' => Str::random(48),
        ]);

        $endpoint->refresh()->loadCount('deliveries');

        return (new WebhookEndpointResource($endpoint))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $endpoint = WebhookEndpoint::withCount('deliveries')->findOrFail($id);

        return (new WebhookEndpointResource($endpoint))->response();
    }

    public function update(int $id): JsonResponse
    {
        $endpoint = WebhookEndpoint::findOrFail($id);
        $endpoint->update(app(UpdateWebhookEndpointRequest::class)->validated());

        return (new WebhookEndpointResource($endpoint->refresh()->loadCount('deliveries')))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        WebhookEndpoint::findOrFail($id)->delete();

        return response()->json(null, 204);
    }

    /**
     * Issue a fresh signing secret, invalidating the previous one.
     */
    public function rotateSecret(int $id): JsonResponse
    {
        $endpoint = WebhookEndpoint::findOrFail($id);
        $endpoint->forceFill(['secret' => Str::random(48)])->save();

        return (new WebhookEndpointResource($endpoint->refresh()->loadCount('deliveries')))->response();
    }

    /**
     * Send a synthetic payload so integrators can confirm their endpoint works.
     */
    public function test(int $id): JsonResponse
    {
        $endpoint = WebhookEndpoint::findOrFail($id);

        $delivery = app(WebhookService::class)->dispatchPayload($endpoint, 'webhook.test', [
            'event' => 'webhook.test',
            'sent_at' => now()->toISOString(),
            'data' => [
                'id' => $endpoint->getKey(),
                'type' => $endpoint->getMorphClass(),
                'company_id' => $endpoint->company_id,
                'attributes' => [
                    'message' => 'This is a test delivery from Nexi ERP.',
                ],
            ],
        ]);

        return (new WebhookDeliveryResource($delivery->refresh()))->response()->setStatusCode(201);
    }

    public function deliveries(int $id): JsonResponse
    {
        $endpoint = WebhookEndpoint::findOrFail($id);

        $query = $endpoint->deliveries()->orderByDesc('id');

        if ($status = request('status')) {
            $query->where('status', $status);
        }

        if ($event = request('event')) {
            $query->where('event', $event);
        }

        $perPage = request()->integer('per_page', 15);

        return WebhookDeliveryResource::collection($query->paginate(min($perPage, 100)))->response();
    }
}
