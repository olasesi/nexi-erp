<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreFormSubmissionRequest;
use App\Http\Requests\Api\V1\UpdateFormSubmissionRequest;
use App\Http\Resources\FormSubmissionResource;
use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class FormSubmissionController extends BaseController
{
    /** @var FormSubmission */
    protected $model;

    protected string $resourceClass = FormSubmissionResource::class;

    protected string $storeRequestClass = StoreFormSubmissionRequest::class;

    protected string $updateRequestClass = UpdateFormSubmissionRequest::class;

    public function __construct()
    {
        $this->model = new FormSubmission;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query();
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderByDesc('created_at')->paginate(min($perPage, 100));

        return FormSubmissionResource::collection($items)->response();
    }

    public function store(): JsonResponse
    {
        $request = app($this->storeRequestClass);
        $data = $request->validated();

        $form = Form::findOrFail((int) $data['form_id']);

        if ($form->status === Form::STATUS_ARCHIVED) {
            throw ValidationException::withMessages([
                'form_id' => 'This form is archived and no longer accepts submissions.',
            ]);
        }

        if ($user = auth('api')->user()) {
            $data['company_id'] = $data['company_id'] ?? $user->company_id;
            $data['submitted_by'] = $data['submitted_by'] ?? $user->id;
        }

        $data['status'] = $data['status'] ?? FormSubmission::STATUS_NEW;

        $submission = $this->model->create($data);

        $form->increment('submissions_count');

        return (new FormSubmissionResource($submission))->response()->setStatusCode(201);
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'form_id', 'status', 'submitted_by'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return [];
    }
}
