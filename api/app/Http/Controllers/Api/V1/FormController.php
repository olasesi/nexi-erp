<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreFormRequest;
use App\Http\Requests\Api\V1\UpdateFormRequest;
use App\Http\Resources\FormResource;
use App\Models\Form;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class FormController extends BaseController
{
    /** @var Form */
    protected $model;

    protected string $resourceClass = FormResource::class;

    protected string $storeRequestClass = StoreFormRequest::class;

    protected string $updateRequestClass = UpdateFormRequest::class;

    public function __construct()
    {
        $this->model = new Form;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query();
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('created_at')->paginate(min($perPage, 100));

        return FormResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['created_by'] = $data['created_by'] ?? $user->id;
        }

        $data['status'] = $data['status'] ?? Form::STATUS_DRAFT;
        $data['is_template'] = $data['is_template'] ?? false;
        $data['submissions_count'] = 0;

        $form = $this->model->create($data);

        return (new FormResource($form))->response()->setStatusCode(201);
    }

    public function publish(int $id): JsonResponse
    {
        $form = $this->model->findOrFail($id);

        if ($form->status === Form::STATUS_ARCHIVED) {
            throw ValidationException::withMessages([
                'status' => 'An archived form cannot be published.',
            ]);
        }

        $form->update(['status' => Form::STATUS_ACTIVE]);

        return (new FormResource($form->fresh()))->response();
    }

    public function archive(int $id): JsonResponse
    {
        $form = $this->model->findOrFail($id);

        if ($form->status === Form::STATUS_ARCHIVED) {
            throw ValidationException::withMessages([
                'status' => 'This form is already archived.',
            ]);
        }

        $form->update(['status' => Form::STATUS_ARCHIVED]);

        return (new FormResource($form->fresh()))->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'status', 'is_template'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['name'];
    }
}
