<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\JournalEntryResource;
use App\Models\JournalEntry;
use App\Services\GeneralLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** @property JournalEntry $model */
class JournalEntryController extends BaseController
{
    protected $model;

    protected string $resourceClass = JournalEntryResource::class;

    protected string $storeRequestClass = 'App\Http\Requests\Api\V1\StoreJournalEntryRequest';

    protected string $updateRequestClass = 'App\Http\Requests\Api\V1\UpdateJournalEntryRequest';

    public function __construct()
    {
        $this->model = new JournalEntry;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()
            ->with('lines')
            ->orderByDesc('entry_date');

        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->paginate(min($perPage, 100));

        return $this->resourceClass::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        $user = auth('api')->user();

        try {
            $entry = app(GeneralLedgerService::class)->post(
                $data['company_id'],
                $data['entry_date'],
                $data['description'] ?? null,
                $data['lines'],
                createdBy: $user?->id,
                status: $data['status'] ?? 'posted',
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return (new $this->resourceClass($entry))->response()->setStatusCode(201);
    }

    public function update(int $id): JsonResponse
    {
        $entry = $this->model->findOrFail($id);

        if ($entry->status !== 'draft') {
            return response()->json(['message' => 'Only draft entries can be edited.'], 422);
        }

        $request = app($this->updateRequestClass);
        $data = $request->validated();

        $GL = app(GeneralLedgerService::class);

        try {
            DB::transaction(function () use ($entry, $data, $GL) {
                $entry->update([
                    'entry_date' => $data['entry_date'] ?? $entry->entry_date,
                    'description' => $data['description'] ?? $entry->description,
                ]);

                if (isset($data['lines'])) {
                    $entry->lines()->delete();
                    $lines = $GL->normalizeLines(
                        $entry->company_id,
                        $data['lines']
                    );

                    $debits = round(array_sum(array_column($lines, 'debit')), 2);
                    $credits = round(array_sum(array_column($lines, 'credit')), 2);

                    if (abs($debits - $credits) > 0.001) {
                        throw new \InvalidArgumentException(
                            "Journal entry is out of balance: debits {$debits} vs credits {$credits}."
                        );
                    }

                    $entry->lines()->createMany($lines);
                }
            });
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return (new $this->resourceClass($entry->fresh('lines')))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $entry = $this->model->findOrFail($id);

        if ($entry->status !== 'draft') {
            return response()->json(['message' => 'Only draft entries can be deleted. Void the entry instead.'], 422);
        }

        $entry->lines()->delete();
        $entry->delete();

        return response()->json(null, 204);
    }

    public function postEntry(int $id): JsonResponse
    {
        $entry = $this->model->with('lines')->findOrFail($id);

        if ($entry->status !== 'draft') {
            return response()->json(['message' => 'Only draft entries can be posted.'], 422);
        }

        $entry->update([
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        return (new $this->resourceClass($entry->fresh('lines')))->response();
    }

    public function void(int $id): JsonResponse
    {
        $entry = $this->model->findOrFail($id);

        if ($entry->status === 'void') {
            return response()->json(['message' => 'Entry is already void.'], 422);
        }

        $entry->update(['status' => 'void']);

        return (new $this->resourceClass($entry->fresh('lines')))->response();
    }

    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'entry_date'];
    }

    protected function getSearchableFields(): array
    {
        return ['entry_number', 'description'];
    }
}
