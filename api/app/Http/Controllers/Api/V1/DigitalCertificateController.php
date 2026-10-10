<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\RevokeCertificateRequest;
use App\Http\Requests\Api\V1\StoreDigitalCertificateRequest;
use App\Http\Requests\Api\V1\UpdateDigitalCertificateRequest;
use App\Http\Resources\DigitalCertificateResource;
use App\Models\DigitalCertificate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class DigitalCertificateController extends BaseController
{
    /** @var DigitalCertificate */
    protected $model;

    protected string $resourceClass = DigitalCertificateResource::class;

    protected string $storeRequestClass = StoreDigitalCertificateRequest::class;

    protected string $updateRequestClass = UpdateDigitalCertificateRequest::class;

    public function __construct()
    {
        $this->model = new DigitalCertificate;
    }

    public function index(): JsonResponse
    {
        $query = $this->applyFilters($this->model->query()->with('employee'));

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('id')->paginate(min($perPage, 100));

        return DigitalCertificateResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['status'] = $data['status'] ?? DigitalCertificate::STATUS_DRAFT;

        $certificate = $this->model->create($data);

        return (new DigitalCertificateResource($certificate->load('employee')))
            ->response()
            ->setStatusCode(201);
    }

    public function generate(int $id): JsonResponse
    {
        $certificate = $this->model->findOrFail($id);

        if ($certificate->status !== DigitalCertificate::STATUS_DRAFT) {
            return response()->json([
                'message' => 'Only a draft certificate can be generated.',
            ], 422);
        }

        $certificate->update([
            'serial' => $certificate->serial ?? (string) Str::uuid(),
            'certificate_number' => $certificate->certificate_number ?? 'CERT-'.strtoupper(Str::random(10)),
            'issued_date' => $certificate->issued_date ?? today(),
            'status' => DigitalCertificate::STATUS_ISSUED,
        ]);

        return (new DigitalCertificateResource($certificate->fresh('employee')))->response();
    }

    public function revoke(int $id): JsonResponse
    {
        $request = app(RevokeCertificateRequest::class);
        $data = $request->validated();

        $certificate = $this->model->findOrFail($id);

        if ($certificate->status !== DigitalCertificate::STATUS_ISSUED) {
            return response()->json([
                'message' => 'Only an issued certificate can be revoked.',
            ], 422);
        }

        $certificate->update([
            'status' => DigitalCertificate::STATUS_REVOKED,
            'revoked_reason' => $data['revoked_reason'],
            'revoked_at' => now(),
        ]);

        return (new DigitalCertificateResource($certificate->fresh('employee')))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'employee_id', 'certificate_type'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['title', 'certificate_number'];
    }
}
