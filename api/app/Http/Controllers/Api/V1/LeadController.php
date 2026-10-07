<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\LeadResource;
use App\Models\Contact;
use App\Models\CrmStage;
use App\Models\Deal;
use App\Models\Lead;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** @property Lead $model */
class LeadController extends BaseController
{
    protected $model;

    protected string $resourceClass = LeadResource::class;

    protected string $storeRequestClass = 'App\Http\Requests\Api\V1\StoreLeadRequest';

    protected string $updateRequestClass = 'App\Http\Requests\Api\V1\UpdateLeadRequest';

    /** @var list<string> */
    protected array $includes = ['contact', 'assignedTo', 'deals'];

    public function __construct()
    {
        $this->model = new Lead;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with($this->includes);
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->paginate(min($perPage, 100));

        return $this->resourceClass::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        $data['status'] = $data['status'] ?? Lead::STATUS_NEW;
        $data['source'] = $data['source'] ?? 'other';

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $lead = $this->model->create($data);
        $lead->load($this->includes);

        return (new $this->resourceClass($lead))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $lead = $this->model->with($this->includes)->findOrFail($id);

        return (new $this->resourceClass($lead))->response();
    }

    public function update(int $id): JsonResponse
    {
        $request = app($this->updateRequestClass);
        $lead = $this->model->findOrFail($id);
        $lead->update($request->validated());

        return (new $this->resourceClass($lead->fresh($this->includes)))->response();
    }

    /**
     * Turn a qualified lead into a real contact sitting on the pipeline. The
     * contact is created once and reused on later calls, and an existing deal
     * is left alone so repeating the call cannot duplicate revenue.
     */
    public function convert(int $id): JsonResponse
    {
        $lead = $this->model->with($this->includes)->findOrFail($id);

        if ($lead->status === Lead::STATUS_CONVERTED) {
            return response()->json(['message' => 'Lead is already converted.'], 422);
        }

        if ($lead->status === Lead::STATUS_LOST) {
            return response()->json(['message' => 'A lost lead cannot be converted.'], 422);
        }

        $stage = CrmStage::query()
            ->where('company_id', $lead->company_id)
            ->orderBy('position')
            ->orderBy('id')
            ->first();

        if ($stage === null) {
            return response()->json([
                'message' => 'Create a pipeline stage before converting a lead.',
            ], 422);
        }

        DB::transaction(function () use ($lead, $stage) {
            if ($lead->contact_id === null) {
                $lead->contact_id = Contact::create($this->contactPayload($lead))->id;
            }

            $lead->status = Lead::STATUS_CONVERTED;
            $lead->converted_at = $lead->converted_at ?? now();
            $lead->save();

            if ($lead->deals->isEmpty()) {
                $lead->deals()->create([
                    'company_id' => $lead->company_id,
                    'contact_id' => $lead->contact_id,
                    'crm_stage_id' => $stage->id,
                    'assigned_to' => $lead->assigned_to,
                    'title' => $lead->organization !== null && $lead->organization !== ''
                        ? $lead->organization
                        : $lead->name,
                    'value' => $lead->value,
                    'status' => Deal::STATUS_OPEN,
                    'created_by' => $lead->created_by,
                ]);
            }
        });

        $lead = $lead->fresh($this->includes);

        WebhookService::dispatch('lead.converted', $lead);

        return (new $this->resourceClass($lead))->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $lead = $this->model->findOrFail($id);
        $lead->delete();

        return response()->json(null, 204);
    }

    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'source', 'assigned_to'];
    }

    protected function getSearchableFields(): array
    {
        return ['name', 'organization', 'email', 'phone'];
    }

    /**
     * Build a contact out of the lead's captured details. The lead name is
     * split on its first space; a single word falls back to the organisation
     * so the required last_name column never ends up empty.
     *
     * @return array<string, mixed>
     */
    private function contactPayload(Lead $lead): array
    {
        $parts = preg_split('/\s+/', trim($lead->name), 2) ?: [''];
        $firstName = $parts[0] !== '' ? $parts[0] : ($lead->organization ?? 'Lead');
        $lastName = trim($parts[1] ?? '');

        if ($lastName === '') {
            $lastName = $lead->organization !== null && $lead->organization !== ''
                ? $lead->organization
                : $firstName;
        }

        return [
            'company_id' => $lead->company_id,
            'type' => 'lead',
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'notes' => $lead->notes,
        ];
    }
}
