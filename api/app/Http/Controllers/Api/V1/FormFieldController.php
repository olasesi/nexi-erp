<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreFormFieldRequest;
use App\Http\Requests\Api\V1\UpdateFormFieldRequest;
use App\Http\Resources\FormFieldResource;
use App\Models\FormField;
use Illuminate\Http\JsonResponse;

class FormFieldController extends BaseController
{
    /** @var FormField */
    protected $model;

    protected string $resourceClass = FormFieldResource::class;

    protected string $storeRequestClass = StoreFormFieldRequest::class;

    protected string $updateRequestClass = UpdateFormFieldRequest::class;

    public function __construct()
    {
        $this->model = new FormField;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query();
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderBy('position')->orderBy('id')->paginate(min($perPage, 100));

        return FormFieldResource::collection($items)->response();
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'form_id'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['label'];
    }
}
