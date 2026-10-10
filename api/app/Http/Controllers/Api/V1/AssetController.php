<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreAssetRequest;
use App\Http\Requests\Api\V1\UpdateAssetRequest;
use App\Http\Resources\AssetResource;
use App\Models\Asset;
use App\Models\AssetAssignment;
use Illuminate\Http\JsonResponse;

class AssetController extends BaseController
{
    /** @var Asset */
    protected $model;

    protected string $resourceClass = AssetResource::class;

    protected string $storeRequestClass = StoreAssetRequest::class;

    protected string $updateRequestClass = UpdateAssetRequest::class;

    public function __construct()
    {
        $this->model = new Asset;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with('category')->withCount('assignments');
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('id')->paginate(min($perPage, 100));

        return AssetResource::collection($items)->response();
    }

    public function show(int $id): JsonResponse
    {
        $asset = $this->model->with('category')->withCount('assignments')->findOrFail($id);

        return (new AssetResource($asset))->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
        }

        $data['condition'] = $data['condition'] ?? Asset::CONDITION_GOOD;
        $data['status'] = $data['status'] ?? Asset::STATUS_AVAILABLE;

        $asset = $this->model->create($data);

        return (new AssetResource($asset->load('category')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Hand an asset to a user or contact. The asset moves to `assigned` and a
     * new, open assignment row records the handover.
     */
    public function assign(int $id): JsonResponse
    {
        $asset = $this->model->findOrFail($id);

        if (in_array($asset->status, [Asset::STATUS_ASSIGNED, Asset::STATUS_RETIRED, Asset::STATUS_LOST], true)) {
            return response()->json([
                'message' => 'This asset cannot be assigned in its current state.',
            ], 422);
        }

        $data = request()->validate([
            'user_id' => ['nullable', 'required_without:contact_id', 'exists:users,id'],
            'contact_id' => ['nullable', 'required_without:user_id', 'exists:contacts,id'],
        ]);

        $asset->update([
            'status' => Asset::STATUS_ASSIGNED,
            'assigned_to' => $data['user_id'] ?? null,
        ]);

        AssetAssignment::query()->create([
            'company_id' => $asset->company_id,
            'asset_id' => $asset->id,
            'user_id' => $data['user_id'] ?? null,
            'contact_id' => $data['contact_id'] ?? null,
            'assigned_at' => now(),
        ]);

        return (new AssetResource($asset->fresh('category')))->response();
    }

    /**
     * Take an assigned asset back: it becomes `available` again and the latest
     * open assignment is stamped with the return time and condition.
     */
    public function returnAsset(int $id): JsonResponse
    {
        $asset = $this->model->findOrFail($id);

        if ($asset->status !== Asset::STATUS_ASSIGNED) {
            return response()->json([
                'message' => 'Only an assigned asset can be returned.',
            ], 422);
        }

        $data = request()->validate([
            'condition_on_return' => 'nullable|string',
        ]);

        $asset->update([
            'status' => Asset::STATUS_AVAILABLE,
            'assigned_to' => null,
        ]);

        AssetAssignment::query()
            ->where('asset_id', $asset->id)
            ->whereNull('returned_at')
            ->latest('assigned_at')
            ->first()
            ?->update([
                'returned_at' => now(),
                'condition_on_return' => $data['condition_on_return'] ?? null,
            ]);

        return (new AssetResource($asset->fresh('category')))->response();
    }

    /**
     * Retire an asset from service for good; any open assignment is closed.
     */
    public function retire(int $id): JsonResponse
    {
        $asset = $this->model->findOrFail($id);

        if ($asset->status === Asset::STATUS_RETIRED) {
            return response()->json([
                'message' => 'This asset is already retired.',
            ], 422);
        }

        $asset->update([
            'status' => Asset::STATUS_RETIRED,
            'assigned_to' => null,
        ]);

        AssetAssignment::query()
            ->where('asset_id', $asset->id)
            ->whereNull('returned_at')
            ->update(['returned_at' => now()]);

        return (new AssetResource($asset->fresh('category')))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'condition', 'asset_category_id'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['name', 'asset_tag', 'serial_number'];
    }
}
